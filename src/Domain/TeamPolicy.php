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
        return self::requirements($settings, $members, $profiles)['eligible'];
    }
    /**
     * @param array<string,string|null> $settings
     * @param list<array<string,string|null>> $members
     * @param array<int,array<string,string>> $profiles
     * @return array{minimum:int,maximum:int,count:int,configuration_valid:bool,size_met:bool,membership_valid:bool,members:list<array{user_id:int,profile_available:bool,unique:bool,requirements:array<string,bool>}>,eligible:bool}
     */
    public static function requirements(array $settings, array $members, array $profiles): array
    {
        $minimum = (int) ($settings['num_teammember_min'] ?? 2);
        $maximum = (int) ($settings['num_teammember'] ?? 3);
        $configurationValid = $minimum >= 1 && $maximum >= $minimum;
        $sizeMet = $configurationValid && count($members) >= $minimum && count($members) <= $maximum;
        $ids = [];
        $report = [];
        $membershipValid = true;
        $allMet = true;
        foreach ($members as $member) {
            $user = (int) ($member['user_id'] ?? 0);
            $unique = $user > 0 && !isset($ids[$user]);
            $available = isset($profiles[$user]);
            $membershipValid = $membershipValid && $unique && $available;
            $ids[$user] = true;
            $checks = [];
            foreach (['paid' => 'dis_fee', 'waiver' => 'dis_waiver'] as $field => $disabled) {
                if (($settings[$disabled] ?? '0') !== '1') {
                    $checks[$field] = ($member[$field] ?? '0') === '1';
                }
            }
            foreach (['shirt_size' => 'dis_shirt_size', 'public_mobile_inf' => 'dis_mobile_inf'] as $field => $disabled) {
                if (($settings[$disabled] ?? '0') !== '1') {
                    $checks[$field] = !in_array($profiles[$user][$field] ?? '', ['', '0'], true);
                }
            }
            $allMet = $allMet && !in_array(false, $checks, true);
            $report[] = ['user_id' => $user, 'profile_available' => $available, 'unique' => $unique, 'requirements' => $checks];
        }
        return ['minimum' => $minimum, 'maximum' => $maximum, 'count' => count($members), 'configuration_valid' => $configurationValid,
            'size_met' => $sizeMet, 'membership_valid' => $membershipValid, 'members' => $report,
            'eligible' => $sizeMet && $membershipValid && $allMet];
    }
}
