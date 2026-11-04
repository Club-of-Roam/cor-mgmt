<?php

declare(strict_types=1);

namespace Cor\Mgmt\WordPress;

/** Accessible native form controls; legacy field metadata remains a compatibility input. */
final class EventForm
{
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
    /** @param array<array<string,mixed>> $sections */
    public static function render(string $type, int $id, array $sections, string $title, string $revision, bool $confirm): void
    {
        $base = add_query_arg('page', 'h3-mgmt-' . $type . 's', admin_url('admin.php'));
        $editable = \H3_MGMT_Security::can_object($type . 's', 'edit', $id) && (!$id || !\H3_MGMT_Security::archived(\H3_MGMT_Security::object_race($type . 's', $id)));
        echo '<div class="wrap"><h1>' . esc_html(html_entity_decode(wp_strip_all_tags($title), ENT_QUOTES, 'UTF-8')) . '</h1><p><a href="' . esc_url($base) . '">' . esc_html__('Back to overview', 'h3-mgmt') . '</a></p>';
        echo '<p>' . esc_html__('Disabled fields are locked by the race phase or your permissions. Save a phase change separately before editing newly available settings.', 'h3-mgmt') . '</p>';
        echo '<form method="post" action="' . esc_url(add_query_arg(['todo' => 'save', 'id' => $id ?: ''], $base)) . '">';
        wp_nonce_field(\H3_MGMT_Security::admin_nonce($type . 's'), 'cor_mgmt_nonce', false);
        foreach (['submitted' => 'y', 'cor_event_revision' => $revision, 'cor_event_confirm' => $confirm ? '1' : '0'] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        foreach ($sections as $section) {
            if (!is_array($section['fields'] ?? null)) {
                continue;
            }
            echo '<details open><summary><strong>' . esc_html(self::text($section['title'] ?? '')) . '</strong></summary><table class="form-table"><tbody>';
            foreach ($section['fields'] as $field) {
                if (is_array($field)) {
                    self::field($type, $field, $editable);
                }
            }
            echo '</tbody></table></details>';
        }
        if ($editable) {
            echo '<p><button type="submit" class="button button-primary">' . esc_html($confirm ? __('Confirm and save despite warning', 'h3-mgmt') : __('Save changes', 'h3-mgmt')) . '</button></p>';
        }
        echo '</form></div>';
    }
    /** @param array<string|int,mixed> $field */
    private static function field(string $resource, array $field, bool $editable): void
    {
        $key = self::text($field['id'] ?? '');
        $label = self::text($field['label'] ?? '') ?: $key;
        $value = self::text($field['value'] ?? '');
        $type = self::text($field['type'] ?? 'text');
        $disabled = !empty($field['disabled']) || !$editable;
        $description = wp_strip_all_tags(self::text($field['desc'] ?? ''));
        $control = 'cor-event-' . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($control) . '">' . esc_html(wp_strip_all_tags($label)) . '</label></th><td>';
        if ($resource === 'race' && $key === 'status') {
            echo '<select';
            self::attributes($control, $key, $disabled);
            echo '>';
            foreach (range(0, 4) as $phase) {
                echo '<option value="' . esc_attr((string) $phase) . '"' . selected($value, (string) $phase, false) . ($phase === 4 && $value !== '4' ? ' disabled' : '') . '>' . esc_html(EventList::phaseLabel($phase)) . '</option>';
            }
            echo '</select>';
        } elseif (in_array($type, ['select', 'radio'], true)) {
            echo '<select';
            self::attributes($control, $key, $disabled);
            echo '>';
            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $option) {
                if (is_array($option)) {
                    $choice = self::text($option['value'] ?? '');
                    echo '<option value="' . esc_attr($choice) . '"' . selected($value, $choice, false) . '>' . esc_html(wp_strip_all_tags(self::text($option['label'] ?? ''))) . '</option>';
                }
            }
            echo '</select>';
        } elseif (str_starts_with($type, 'textarea')) {
            echo '<textarea class="large-text" rows="5"';
            self::attributes($control, $key, $disabled);
            echo '>' . esc_textarea($value) . '</textarea>';
        } else {
            $inputType = match ($type) {
                'date' => 'date', 'password' => 'password', default => 'text'
            };
            if ($inputType === 'password') {
                $value = '';
            }
            echo '<input class="regular-text" type="' . esc_attr($inputType) . '"';
            self::attributes($control, $key, $disabled);
            echo ' value="' . esc_attr($value) . '"' . ($inputType === 'password' ? ' autocomplete="new-password"' : '') . '>';
        }
        echo '<p class="description" id="' . esc_attr($control . '-help') . '">' . esc_html($description) . '</p></td></tr>';
    }
    private static function attributes(string $control, string $key, bool $disabled): void
    {
        echo ' id="' . esc_attr($control) . '" name="' . esc_attr($key) . '" aria-describedby="' . esc_attr($control . '-help') . '"' . ($disabled ? ' disabled' : '');
    }
}
