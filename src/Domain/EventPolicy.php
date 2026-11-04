<?php

declare(strict_types=1);

namespace Cor\Mgmt\Domain;

/** Field rules, independent of HTTP, WordPress and storage. Unknown fields fail closed. */
final class EventPolicy
{
    /** @return array<string,int> Last editable phase per field. */
    public static function fields(string $type): array
    {
        return match ($type) {
            'race' => [
                'name' => 2, 'logo_url' => 2, 'information_text' => 2,
                'start' => 3, 'end' => 3, 'active' => 3, 'status' => 3,
                'startingpoint' => 1, 'start_route_id' => 1,
                'num_teammember' => 0, 'num_teammember_min' => 0,
                'dis_shirt_size' => 0, 'dis_mobile_inf' => 0, 'dis_waiver' => 0, 'dis_fee' => 0,
                'liveticker' => 3, 'liveticker_front' => 3, 'donation' => 3,
                'one_extra_point' => 2, 'amount_extra_point' => 2, 'extra_point_amount' => 2, 'vary_extra_point_field' => 2,
                'kind_of_donation_tool' => 2, 'betterplace_redirect_link' => 2, 'donation_link_link' => 2,
                'show_donation_amount' => 2, 'team_overview_link' => 2,
                'question_1_invisible' => 0, 'question_2_invisible' => 0, 'question_3_invisible' => 0,
                'question_4_invisible' => 0, 'question_5_invisible' => 0,
            ],
            'route' => ['race_id' => 0, 'name' => 2, 'max_teams' => 1, 'color_code' => 2, 'logo_url' => 2, 'user_id' => 3],
            'stage' => ['race_id' => 0, 'route_id' => 0, 'number' => 0, 'destination' => 0, 'country' => 0,
                'country_3166_alpha-2' => 0, 'meeting_point' => 3],
            default => [],
        };
    }

    public static function allows(string $type, string $field, RacePhase $phase): bool
    {
        return isset(self::fields($type)[$field]) && $phase->value <= self::fields($type)[$field];
    }

    /** @return list<string> Stable warning codes; never mutate assignments. */
    public static function capacityWarnings(int $capacity, int $completeTeams): array
    {
        return $capacity < $completeTeams ? ['capacity_below_occupancy'] : [];
    }
}
