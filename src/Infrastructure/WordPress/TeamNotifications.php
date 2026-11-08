<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Application\Contracts\Clock;
use Cor\Mgmt\Domain\MailRequest;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\WordPress\TeamPortal;

/** Snapshots recipients while the team transaction and its common lock are still held. */
final class TeamNotifications
{
    public function __construct(private readonly Database $db, private readonly Clock $clock)
    {
    }

    /** @param list<array<string,mixed>> $events */
    public function capture(array $events): void
    {
        if (!$events) {
            return;
        }
        $queue = new MailQueue($this->db, $this->clock);
        $queue->controlled(static fn (): bool => true);
        foreach ($events as $event) {
            $this->db->assertLock();
            $repo = new ParticipationRepository($this->db);
            $team = $repo->find('team', (int) ($event['team'] ?? 0));
            $race = $repo->find('race', (int) ($team['race_id'] ?? 0));
            $action = $event['action'] ?? '';
            if (!$race || !in_array($action, ['team-creation','invitation','joined','publishable'], true)) {
                throw new CommunicationException('invalid_team_notification');
            }
            $members = $repo->related('teammate', 'team_id', (string) $team['id']);
            $actor = (int) ($event['user'] ?? 0);
            $actorProfile = $actor ? $repo->profile($actor) : [];
            $language = 'en';
            $names = [];
            foreach ($members as $member) {
                $names[] = $repo->profile((int) $member['user_id'])['first_name'] ?? '';
                if ((int) $member['user_id'] === $actor) {
                    $language = $member['language'] ?: 'und';
                }
            }
            $metadata = ['team' => (int) $team['id'], 'action' => $action, 'user' => $actor, 'token_hash' => $action === 'invitation' && is_string($event['code'] ?? null) ? hash('sha256', $event['code']) : null];
            $this->db->execute($this->db->prepare("INSERT INTO %i (race_id,actor_id,created_at,kind,payload) VALUES (%d,%d,%d,'team_event',%s)", $this->db->table('cor_mgmt_communication_history'), (int) $race['id'], $actor, $this->clock->now(), json_encode($metadata, JSON_THROW_ON_ERROR)));
            $occurrence = 'team-event:' . $this->db->wpdb->insert_id;
            $values = ['name' => '', 'names' => implode(', ', $names), 'team_name' => $team['team_name'] ?? '', 'event_name' => $race['name'] ?? '', 'inviter' => $actorProfile['first_name'] ?? implode(', ', $names), 'invitee' => $actorProfile['first_name'] ?? '', 'link' => TeamPortal::url((int) $race['id']), 'code' => '', 'donation' => '10', 'thumbs' => '1'];
            if ($action === 'invitation') {
                $values['name'] = $actorProfile['first_name'] ?? '';
                $values['code'] = (string) ($event['code'] ?? '');
                $values['link'] = add_query_arg('invitation', $values['code'], $values['link']);
                $queue->enqueueWithinTransaction(new MailRequest((int) $race['id'], $occurrence, 'invitation', (string) ($event['email'] ?? ''), $language, $values));
                continue;
            }
            foreach ($members as $member) {
                $user = (int) $member['user_id'];
                if ($action === 'team-creation' && $user !== $actor) {
                    continue;
                }
                $recipient = $repo->find('user', $user);
                $values['name'] = $repo->profile($user)['first_name'] ?? '';
                if ($action === 'joined') {
                    // Old tokens do not record their sender. Use the other team members, never guess a user ID.
                    $others = array_filter($members, static fn (array $row): bool => (int) $row['user_id'] !== $actor);
                    $values['inviter'] = implode(', ', array_map(static fn (array $row): string => $repo->profile((int) $row['user_id'])['first_name'] ?? '', $others));
                    if (is_string($event['invitation_hash'] ?? null)) {
                        $source = $this->db->rows($this->db->prepare("SELECT actor_id FROM %i WHERE race_id=%d AND kind='team_event' AND JSON_UNQUOTE(JSON_EXTRACT(CONVERT(payload USING utf8mb4),'$.token_hash'))=%s ORDER BY id LIMIT 1", $this->db->table('cor_mgmt_communication_history'), (int) $race['id'], $event['invitation_hash']))[0] ?? [];
                        if ((int) ($source['actor_id'] ?? 0) > 0) {
                            $values['inviter'] = $repo->profile((int) $source['actor_id'])['first_name'] ?? '';
                        }
                    }
                }
                $mailAction = $action === 'joined' ? ($user === $actor ? 'invitation-accepted-invitee' : 'invitation-accepted-inviter') : $action;
                $queue->enqueueWithinTransaction(new MailRequest((int) $race['id'], $occurrence, $mailAction, $recipient['user_email'] ?? '', $member['language'] ?: 'und', $values));
            }
        }
    }
}
