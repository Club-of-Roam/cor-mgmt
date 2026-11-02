<?php

declare(strict_types=1);

// Only for isolated tests. Refuse to replace a running WordPress environment.
if (function_exists('get_user_meta') || function_exists('_x') || defined('ABSPATH')) {
    throw new RuntimeException('Legacy doubles require a process without WordPress.');
} else {
    function get_user_meta($userId, $key, $single = false)
    {
        return $GLOBALS['cor_test_user_meta'][$userId][$key] ?? '';
    }
    function _x($text, $context, $domain = 'default') { return $text; }
    function __($text, $domain = 'default') { return $text; }
    function shortcode_atts($defaults, $attributes) { return array_intersect_key((array) $attributes, $defaults) + $defaults; }
    function get_post() { return (object) ['ID' => 123]; }
    function get_page_link($id) { return 'https://example.invalid/ranking'; }
    function get_site_url() { return 'https://example.invalid'; }
    function wp_parse_args($args, $defaults = [])
    {
        if (!is_array($args)) { throw new RuntimeException('This isolated boundary accepts array arguments only.'); }
        return array_merge($defaults, $args);
    }
}
define('ARRAY_A', 'ARRAY_A');
