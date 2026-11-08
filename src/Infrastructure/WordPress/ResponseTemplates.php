<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Domain\AutoResponseTemplate;
use Cor\Mgmt\Infrastructure\WordPress\Schema\CommunicationSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;

/** Event-scoped configuration. No mail transport and no active-race fallback. */
final class ResponseTemplates
{
    /** @var \Closure(int,int,string):bool */
    private readonly \Closure $authorize;
    /** @param callable(int,int,string):bool $authorize */
    public function __construct(private readonly Database $db, private readonly Clock $clock, callable $authorize)
    {
        $this->authorize = \Closure::fromCallable($authorize);
    }
    private function allowed(int $race, int $actor, string $operation): void
    {
        if ($actor < 1 || !($this->authorize)($race, $actor, $operation)) {
            throw new CommunicationException('forbidden');
        }
    }
    private function ready(): void
    {
        if (!CommunicationSchema::ready($this->db)) {
            throw new CommunicationException('communication_setup_required');
        }
    }
    /** @return array{race:array<string,string|null>,templates:list<array<string,string|null>>,revision:string,writable:bool} */
    public function context(int $race, int $actor): array
    {
        $this->allowed($race, $actor, 'view');
        $this->ready();
        $row = $this->db->rows($this->db->prepare('SELECT id,name,setting FROM %i WHERE id=%d', $this->db->table('h3_mgmt_races'), $race))[0] ?? [];
        $rules = json_decode($row['setting'] ?? '', true);
        if (!$row || !is_array($rules) || !in_array($rules['status'] ?? null, [0,1,2,3,4,'0','1','2','3','4'], true)) {
            throw new CommunicationException('invalid_event');
        }
        $templates = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE race_id=%d ORDER BY action,language', $this->db->table('cor_mgmt_response_templates'), $race));
        $generation = $this->db->scalar($this->db->prepare('SELECT MAX(id) FROM %i WHERE race_id=%d', $this->db->table('cor_mgmt_communication_history'), $race));
        return ['race' => $row, 'templates' => $templates, 'revision' => hash('sha256', json_encode([$row,$templates,$generation], JSON_THROW_ON_ERROR)), 'writable' => (int) $rules['status'] !== 4 && ($this->authorize)($race, $actor, 'edit')];
    }
    /** Strict resolution primitive for the forthcoming sender. Missing never means global. */
    public function resolve(int $race, string $action, string $language): ?AutoResponseTemplate
    {
        $this->ready();
        AutoResponseTemplate::key($action, $language);
        $language = AutoResponseTemplate::language($language);
        $row = $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE race_id=%d AND action=%s AND language=%s', $this->db->table('cor_mgmt_response_templates'), $race, $action, $language))[0] ?? null;
        if ($row === null) {
            return null;
        }
        return new AutoResponseTemplate($action, $language, $row['enabled'] === '1', $row['subject'] ?? '', $row['message'] ?? '');
    }
    /** @param callable():array<string,string> $operation
     * @return array<string,string>
     */
    private function atomic(callable $operation): array
    {
        return (new LegacyEventStore($this->db))->exclusive(function () use ($operation): array {
            $this->ready();
            foreach (CommunicationSchema::definitions() as $suffix => $definition) {
                (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
            }
            return $operation();
        }, true);
    }
    /** @return array{race:array<string,string|null>,templates:list<array<string,string|null>>,revision:string,writable:bool} */
    private function writable(int $race, int $actor): array
    {
        $this->allowed($race, $actor, 'edit');
        $context = $this->context($race, $actor);
        if (!$context['writable']) {
            throw new CommunicationException('archive_locked');
        }
        return $context;
    }
    /** @return array<string,string> */
    public function save(int $race, int $actor, AutoResponseTemplate $template, string $revision): array
    {
        return $this->atomic(function () use ($race, $actor, $template, $revision): array {
            $context = $this->writable($race, $actor);
            if (!hash_equals($context['revision'], $revision)) {
                throw new CommunicationException('stale_templates');
            }
            $old = null;
            foreach ($context['templates'] as $candidate) {
                if ($candidate['action'] === $template->action && $candidate['language'] === $template->language) {
                    $old = $candidate;
                    break;
                }
            }
            if ($old !== null && $old['enabled'] === (string) (int) $template->enabled && $old['subject'] === $template->subject && $old['message'] === $template->message) {
                return ['status' => 'unchanged'];
            }
            if ($old !== null && (int) $old['revision'] >= PHP_INT_MAX) {
                throw new CommunicationException('revision_exhausted');
            }
            $this->put($race, $template, $old === null ? 1 : (int) $old['revision'] + 1, $old !== null);
            $this->journal($race, $actor, 'template_saved', ['old' => $old, 'new' => ['action' => $template->action,'language' => $template->language,'enabled' => $template->enabled,'subject' => $template->subject,'message' => $template->message]]);
            return ['status' => 'saved'];
        });
    }
    private function put(int $race, AutoResponseTemplate $template, int $revision, bool $exists): void
    {
        $this->db->assertLock();
        if ($exists) {
            $this->db->execute($this->db->prepare('UPDATE %i SET enabled=%d,subject=%s,message=%s,revision=%d,updated_at=%d WHERE race_id=%d AND action=%s AND language=%s', $this->db->table('cor_mgmt_response_templates'), (int) $template->enabled, $template->subject, $template->message, $revision, $this->clock->now(), $race, $template->action, $template->language));
        } else {
            $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,action,language,enabled,subject,message,revision,updated_at) VALUES (%d,%s,%s,%d,%s,%s,%d,%d)', $this->db->table('cor_mgmt_response_templates'), $race, $template->action, $template->language, (int) $template->enabled, $template->subject, $template->message, $revision, $this->clock->now()));
        }
    }
    /** @return array{fingerprint:string,rows:list<array{action:string,language:string,status:string,enabled:bool,subject:string,message:string}>,issues:list<string>} */
    public function previewTransfer(int $target, int $source, int $actor): array
    {
        $context = $this->writable($target, $actor);
        if ($source === $target || $source < 0) {
            throw new CommunicationException('invalid_source');
        }
        $sourceRevision = null;
        if ($source === 0) {
            $this->allowed(0, $actor, 'legacy');
            $originals = $this->db->rows($this->db->prepare('SELECT * FROM %i ORDER BY id', $this->db->table('h3_mgmt_auto_responses')));
        } else {
            $sourceContext = $this->context($source, $actor);
            $originals = $sourceContext['templates'];
            $sourceRevision = $sourceContext['revision'];
        }
        $counts = [];
        foreach ($originals as $row) {
            $key = ($row['action'] ?? '') . ':' . strtolower(str_replace('-', '_', $row['language'] ?? ''));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        $existing = [];
        foreach ($context['templates'] as $row) {
            $existing[($row['action'] ?? '') . ':' . ($row['language'] ?? '')] = true;
        }
        $rows = [];
        $issues = [];
        foreach ($originals as $row) {
            $action = $row['action'] ?? '';
            $language = $row['language'] ?? '';
            $key = $action . ':' . strtolower(str_replace('-', '_', $language));
            try {
                $switch = $source === 0 ? ($row['switch'] ?? '') : ($row['enabled'] ?? '');
                if (!in_array($switch, ['0','1'], true)) {
                    throw new CommunicationException('invalid_switch');
                }
                $template = new AutoResponseTemplate($action, $language, $switch === '1', $row['subject'] ?? '', $row['message'] ?? '');
                if ($counts[$key] !== 1) {
                    throw new CommunicationException('duplicate_source_template');
                }
                $rows[] = ['action' => $action, 'language' => $template->language, 'status' => isset($existing[$key]) ? 'keep_existing' : 'create', 'enabled' => $template->enabled, 'subject' => $template->subject, 'message' => $template->message];
            } catch (CommunicationException $error) {
                $issues[] = $action . ':' . $language . ':' . $error->reason;
            }
        }
        return ['fingerprint' => hash('sha256', json_encode([$target,$source,$context['revision'],$sourceRevision,$originals], JSON_THROW_ON_ERROR)), 'rows' => $rows, 'issues' => $issues];
    }
    /** @return array<string,string> */
    public function transfer(int $target, int $source, int $actor, string $fingerprint): array
    {
        return $this->atomic(function () use ($target, $source, $actor, $fingerprint): array {
            $preview = $this->previewTransfer($target, $source, $actor);
            if (!hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new CommunicationException('stale_transfer');
            }
            if ($preview['issues']) {
                throw new CommunicationException('source_review_required');
            }
            $created = [];
            foreach ($preview['rows'] as $row) {
                if ($row['status'] === 'create') {
                    $this->put($target, new AutoResponseTemplate($row['action'], $row['language'], $row['enabled'], $row['subject'], $row['message']), 1, false);
                    $created[] = $row;
                }
            }
            if ($created) {
                $this->journal($target, $actor, 'templates_copied', ['source' => $source, 'fingerprint' => $fingerprint, 'created' => $created]);
            }
            return ['created' => (string) count($created)];
        });
    }
    /** @param array<string,mixed> $payload */
    private function journal(int $race, int $actor, string $kind, array $payload): void
    {
        $this->db->assertLock();
        $this->db->execute($this->db->prepare('INSERT INTO %i (race_id,actor_id,created_at,kind,payload) VALUES (%d,%d,%d,%s,%s)', $this->db->table('cor_mgmt_communication_history'), $race, $actor, $this->clock->now(), $kind, json_encode($payload, JSON_THROW_ON_ERROR)));
    }
}
