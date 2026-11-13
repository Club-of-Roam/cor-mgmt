<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

/** Consistent headings and useful empty states for the six event page shortcodes. */
final class EventPageView
{
    public static function register(): void
    {
        // ArchiveFrontend may already have supplied a sealed, read-only view.
        add_filter('pre_do_shortcode_tag', [self::class, 'before'], 20, 3);
        add_filter('do_shortcode_tag', [self::class, 'after'], 20, 3);
    }

    /** @param array<string,mixed>|string $attributes */
    private static function race(string $kind, array|string $attributes): int
    {
        $attributes = is_array($attributes) ? $attributes : [];
        if (in_array($kind, ['ticker', 'map'], true)) {
            return TickerBoundary::race($attributes);
        }
        $value = $attributes['race'] ?? ($kind === 'results' ? 1 : 0);
        if ($kind === 'team' && !$value) {
            $value = $attributes['event'] ?? 1;
        }
        global $h3_mgmt_races;
        return $value === 'active' ? (int) $h3_mgmt_races->get_active_race() : \H3_MGMT_Security::id($value);
    }

    /** @return array<string,string|null> */
    private static function event(int $race): array
    {
        if ($race < 1) {
            return [];
        }
        try {
            return ArchiveFrontend::frozen($race, 'race', $race) ?? TeamBoundary::repository()->find('race', $race);
        } catch (\Throwable $error) {
            Services::get()->logger->error('event_page_context_failed', $error);
            return [];
        }
    }

    /** @param array<string,mixed>|string $attributes */
    public static function before(mixed $output, string $tag, array|string $attributes): mixed
    {
        $kind = SitePages::kind($tag);
        if ($kind === null) {
            return $output;
        }
        $race = self::race($kind, $attributes);
        $event = self::event($race);
        if ($event === []) {
            return self::frame($event, $kind, '<p>' . esc_html__('This event is unavailable. Please check the event assigned to this page.', 'h3-mgmt') . '</p>');
        }
        return $output === false ? false : self::frame($event, $kind, (string) $output);
    }

    /** @param array<string,mixed>|string $attributes */
    public static function after(string $output, string $tag, array|string $attributes): string
    {
        $kind = SitePages::kind($tag);
        if ($kind === null || ($kind === 'team' && str_starts_with($output, '<section class="cor-team-dashboard"><h2>'))) {
            return $output;
        }
        return self::frame(self::event(self::race($kind, $attributes)), $kind, $output);
    }

    /** @param array<string,string|null> $event */
    private static function frame(array $event, string $kind, string $output): string
    {
        if (trim(wp_strip_all_tags($output)) === '' && !preg_match('/<(?:img|video|audio|iframe|canvas|svg)\b/i', $output)) {
            $message = match ($kind) {
                'team' => __('Log in to manage your team.', 'h3-mgmt'),
                'teams' => __('No teams meet the participation requirements yet.', 'h3-mgmt'),
                'results' => __('No results are available yet.', 'h3-mgmt'),
                'ticker' => __('No messages sent yet.', 'h3-mgmt'),
                'map' => __('No locations have been shared yet.', 'h3-mgmt'),
                'donations' => __('Donations are currently disabled for this event.', 'h3-mgmt'),
                default => __('No content is available yet.', 'h3-mgmt'),
            };
            $output = '<p>' . esc_html($message) . '</p>';
        }
        $name = trim((string) ($event['name'] ?? '')) ?: __('Event', 'h3-mgmt');
        return '<section class="cor-event-page" data-event-page="' . esc_attr($kind) . '"><h2>' . esc_html($name) . '</h2><h3>' . esc_html(SitePages::label($kind)) . '</h3>' . $output . '</section>';
    }
}
