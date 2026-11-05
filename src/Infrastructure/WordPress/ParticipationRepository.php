<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Teams\ParticipationStore;
use Cor\Mgmt\Application\Teams\ParticipationException;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\ParticipationSchema;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Inspector;

final class ParticipationRepository implements ParticipationStore
{
    private bool $writing = false;
    /** @var array<int,true> */
    private array $touched = [];
    public const PROFILE_FIELDS = ['first_name','last_name','city','birthday','mobile','shirt_size','public_mobile_inf','addressMobile','cor_mgmt_avatar'];
    public function __construct(private readonly Database $db)
    {
    }
    private function table(string $type): string
    {
        return match ($type) {
            'race', 'route', 'stage', 'team', 'teammate', 'invitation', 'sponsor' => $this->db->table('h3_mgmt_' . $type . 's'),
            'token' => $this->db->table('cor_mgmt_invitation_tokens'),
            'history' => $this->db->table('cor_mgmt_membership_history'),
            'user' => $this->db->wpdb->users,
            default => throw new ParticipationException('invalid_resource'),
        };
    }
    public function find(string $type, int $id): array
    {
        return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE id=%d', $this->table($type), $id) . ($this->writing ? ' FOR UPDATE' : ''))[0] ?? [];
    }
    public function related(string $type, string $field, string $value): array
    {
        if (!in_array($field, ['team_id','user_id','race_id','route_id','token_hash','email','code'], true)) {
            throw new ParticipationException('invalid_filter');
        }
        return $this->db->rows($this->db->prepare('SELECT * FROM %i WHERE %i=%s ORDER BY id', $this->table($type), $field, $value) . ($this->writing ? ' FOR UPDATE' : ''));
    }
    /** @param array<string,string|null> $values */
    private function assignments(array $values): string
    {
        $parts = [];
        foreach ($values as $field => $value) {
            $parts[] = $value === null ? $this->db->prepare('%i=NULL', $field) : $this->db->prepare('%i=%s', $field, $value);
        }
        return implode(',', $parts);
    }
    public function insert(string $type, array $values): int
    {
        $this->db->assertLock();
        $this->db->execute($this->db->prepare('INSERT INTO %i SET ', $this->table($type)) . $this->assignments($values));
        return (int) $this->db->wpdb->insert_id;
    }
    public function update(string $type, array $old, array $values): void
    {
        $this->db->assertLock();
        if ($type === 'team' && isset($values['route_id']) && $values['route_id'] !== '0' && $values['route_id'] !== ($old['route_id'] ?? null) && $this->db->exists($this->db->table('cor_mgmt_results')) && $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE team_id=%d AND route_id<>%d', $this->db->table('cor_mgmt_results'), (int) ($old['id'] ?? 0), (int) $values['route_id'])) !== '0') {
            throw new ParticipationException('results_prevent_route_change');
        }
        $changed = array_filter($values, static fn (?string $value, string $key): bool => !array_key_exists($key, $old) || $old[$key] !== $value, ARRAY_FILTER_USE_BOTH);
        if ($changed === []) {
            return;
        }
        $where = [];
        foreach ($old as $key => $value) {
            $where[] = $value === null ? $this->db->prepare('%i IS NULL', $key) : $this->db->prepare('CAST(%i AS BINARY)=CAST(%s AS BINARY)', $key, $value);
        }
        if (!$where) {
            throw new ParticipationException('missing_record');
        }
        $this->db->execute($this->db->prepare('UPDATE %i SET ', $this->table($type)) . $this->assignments($changed) . ' WHERE ' . implode(' AND ', $where));
        if ($this->db->wpdb->rows_affected !== 1) {
            throw new ParticipationException('stale_record');
        }
    }
    public function remove(string $type, array $old): void
    {
        $this->db->assertLock();
        if ($type === 'team' && $this->db->exists($this->db->table('cor_mgmt_results')) && $this->db->scalar($this->db->prepare('SELECT COUNT(*) FROM %i WHERE team_id=%d', $this->db->table('cor_mgmt_results'), (int) ($old['id'] ?? 0))) !== '0') {
            throw new ParticipationException('dependent_records');
        }
        $this->db->execute($this->db->prepare('DELETE FROM %i WHERE id=%d', $this->table($type), (int) ($old['id'] ?? 0)));
        if ($this->db->wpdb->rows_affected !== 1) {
            throw new ParticipationException('stale_record');
        }
    }
    public function profile(int $user): array
    {
        $profile = [];
        if ($this->find('user', $user) === []) {
            return [];
        }
        foreach (self::PROFILE_FIELDS as $field) {
            $value = get_user_meta($user, $field, true);
            $profile[$field] = is_scalar($value) ? (string) $value : '';
        }
        return $profile;
    }
    public function saveProfile(int $user, array $values): void
    {
        $this->db->assertLock();
        foreach ($values as $field => $value) {
            if (!in_array($field, self::PROFILE_FIELDS, true)) {
                throw new ParticipationException('invalid_profile_field');
            }
            $rows = $this->db->rows($this->db->prepare('SELECT umeta_id FROM %i WHERE user_id=%d AND meta_key=%s FOR UPDATE', $this->db->wpdb->usermeta, $user, $field));
            if ($rows) {
                $this->db->execute($this->db->prepare('UPDATE %i SET meta_value=%s WHERE user_id=%d AND meta_key=%s', $this->db->wpdb->usermeta, $value, $user, $field));
            } else {
                $this->db->execute($this->db->prepare('INSERT INTO %i (user_id,meta_key,meta_value) VALUES (%d,%s,%s)', $this->db->wpdb->usermeta, $user, $field, $value));
            }
        }
        $this->touched[$user] = true;
        wp_cache_delete($user, 'user_meta');
    }
    public function atomic(callable $operation): array
    {
        try {
            $guarded = function () use ($operation): array {
                $this->db->assertLock();
                if (!ParticipationSchema::ready($this->db)) {
                    throw new ParticipationException('participation_setup_required');
                }
                foreach (ParticipationSchema::definitions() as $suffix => $definition) {
                    (new Inspector($this->db))->verify($this->db->table($suffix), $definition);
                }
                foreach ([$this->table('teammate'), $this->db->wpdb->users, $this->db->wpdb->usermeta] as $table) {
                    if ($this->db->scalar($this->db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) !== 'InnoDB') {
                        throw new ParticipationException('transactional_storage_required');
                    }
                }
                $this->writing = true;
                return $operation();
            };
            if ($this->db->hasLock()) {
                if ($this->db->scalar('SELECT @@in_transaction') !== '1') {
                    throw new ParticipationException('transactional_storage_required');
                }
                $this->db->execute('SAVEPOINT cor_participation_edit');
                try {
                    $result = $guarded();
                    $this->db->assertLock();
                    $this->db->execute('RELEASE SAVEPOINT cor_participation_edit');
                    return $result;
                } catch (\Throwable $error) {
                    $this->db->execute('ROLLBACK TO SAVEPOINT cor_participation_edit');
                    $this->db->execute('RELEASE SAVEPOINT cor_participation_edit');
                    throw $error;
                }
            }
            return (new LegacyEventStore($this->db))->exclusive($guarded, true);
        } finally {
            $this->writing = false;
            foreach ($this->touched as $user => $_) {
                wp_cache_delete($user, 'user_meta');
                clean_user_cache($user);
            }
            $this->touched = [];
        }
    }
}
