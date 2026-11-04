<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

final class TeamPolicy
{
    public static function allows(string $action, RacePhase $phase, bool $organizer = false): bool
    {
        if ($phase === RacePhase::Archived) {
            return false;
        }
        if ($organizer && in_array($action, ['create', 'name', 'content', 'membership', 'route'], true)) {
            return true;
        }
        return match ($action) {
            'create', 'invite', 'accept', 'name' => $phase === RacePhase::RegistrationOpen,
            'leave' => in_array($phase, [RacePhase::RegistrationOpen, RacePhase::RegistrationClosed], true),
            'content' => $phase->value >= 1,
            'profile' => true,
            'confirm' => $phase->value < 3,
            'route' => in_array($phase, [RacePhase::RegistrationOpen, RacePhase::RegistrationClosed], true),
            default => false,
        };
    }
    /**
     * @param array<string,string|null> $settings
     * @param list<array<string,string|null>> $members
     * @param array<int,array<string,string>> $profiles
     */
    public static function eligible(array $settings, array $members, array $profiles): bool
    {
        $minimum = (int) ($settings['num_teammember_min'] ?? 2);
        $maximum = (int) ($settings['num_teammember'] ?? 3);
        if ($minimum < 1 || $maximum < $minimum || count($members) < $minimum || count($members) > $maximum) {
            return false;
        }
        $ids = [];
        foreach ($members as $member) {
            $user = (int) ($member['user_id'] ?? 0);
            if (!$user || isset($ids[$user]) || !isset($profiles[$user])) {
                return false;
            }
            $ids[$user] = true;
            foreach (['paid' => 'dis_fee', 'waiver' => 'dis_waiver'] as $field => $disabled) {
                if (($settings[$disabled] ?? '0') !== '1' && ($member[$field] ?? '0') !== '1') {
                    return false;
                }
            }
            foreach (['shirt_size' => 'dis_shirt_size', 'public_mobile_inf' => 'dis_mobile_inf'] as $field => $disabled) {
                if (($settings[$disabled] ?? '0') !== '1' && in_array($profiles[$user][$field] ?? '', ['', '0'], true)) {
                    return false;
                }
            }
        }
        return true;
    }
}
