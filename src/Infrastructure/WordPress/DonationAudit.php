<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Domain\EuroAmount;
use Cor\Mgmt\Infrastructure\WordPress\Schema\Database;
use Cor\Mgmt\Infrastructure\WordPress\Schema\DonationSchema;

/** Aggregate, read-only review. Method names alone never establish historical amount units. */
final class DonationAudit
{
    /** @return array<string,mixed> */
    public static function report(Database $db): array
    {
        $ready = DonationSchema::ready($db);
        $legacy = $db->rows($db->prepare('SELECT method,paid,COUNT(*) AS records FROM %i GROUP BY method,paid ORDER BY method,paid', $db->table('h3_mgmt_sponsors')));
        $orphans = $db->scalar($db->prepare('SELECT COUNT(*) FROM %i s LEFT JOIN %i t ON t.id=s.team_id LEFT JOIN %i r ON r.id=s.race_id WHERE t.id IS NULL OR r.id IS NULL OR t.race_id<>s.race_id', $db->table('h3_mgmt_sponsors'), $db->table('h3_mgmt_teams'), $db->table('h3_mgmt_races')));
        $known = [];
        $total = new EuroAmount(0);
        if ($ready) {
            $known = $db->rows($db->prepare('SELECT state,COUNT(*) AS records FROM %i GROUP BY state ORDER BY state', $db->table('cor_mgmt_donations')));
            foreach ($db->rows($db->prepare("SELECT amount_minor FROM %i WHERE state='confirmed'", $db->table('cor_mgmt_donations'))) as $row) {
                $total = $total->add(new EuroAmount((int) $row['amount_minor']));
            }
        }
        return ['read_only' => true, 'module_ready' => $ready, 'compatibility_records' => $legacy, 'orphan_relations' => (int) $orphans, 'modern_states' => $known, 'modern_observed_minor' => $total->cents, 'currency' => 'EUR', 'historical_units' => 'unresolved_without_source_provenance', 'settlement_and_refunds' => 'not_proven_by_public_opinions'];
    }
}
