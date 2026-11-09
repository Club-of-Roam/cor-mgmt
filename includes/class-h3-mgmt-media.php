<?php

/** Authenticated media boundary; no executable upload script is needed. */
final class H3_MGMT_Media {
    public static function register(): void {
        add_action('wp_ajax_cor_mgmt_upload_image', [self::class, 'ajax_upload']);
        add_action('admin_post_cor_mgmt_profile_layer', [self::class, 'profile_layer']);
        add_filter('pre_do_shortcode_tag', static function ($result, $tag) {
            if ($tag !== 'profile_layer') { return $result; }
            if (!is_user_logged_in()) { return '<p>' . esc_html__('Please log in to upload an image.', 'h3-mgmt') . '</p>'; }
            return '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">' .
                H3_MGMT_Security::nonce('cor-mgmt-profile-layer') . '<input type="hidden" name="action" value="cor_mgmt_profile_layer">' .
                '<label>' . esc_html__('Select image to upload', 'h3-mgmt') . ' <input type="file" name="fileToUpload" accept="image/jpeg,image/png,image/gif,image/webp" required></label>' .
                '<button type="submit">' . esc_html__('Upload image', 'h3-mgmt') . '</button></form>';
        }, 10, 2);
    }

    /** Compatibility adapter for the separate historical overlayer_maker plugin. */
    public static function profile_layer(): void {
        if (!is_user_logged_in() || !H3_MGMT_Security::post('cor-mgmt-profile-layer')) {
            wp_die(esc_html__('Upload not permitted.', 'h3-mgmt'), '', ['response'=>403]);
        }
        $file = H3_MGMT_Security::upload('fileToUpload');
        if (is_wp_error($file)) { wp_die(esc_html($file->get_error_message()), '', ['response'=>400]); }
        $layer = WP_CONTENT_DIR . '/uploads/Profile_Layer_Pics/VCA_ori.png';
        $result = self::compose_layer($file['file'], $layer);
        wp_delete_file($file['file']);
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', ['response'=>400]); }
        if (!self::attach($result, 0)) { wp_die(esc_html__('Image registration failed.', 'h3-mgmt'), '', ['response'=>500]); }
        wp_safe_redirect($result['url']);
        exit;
    }

    public static function compose_layer(string $source, string $layer) {
        if (!function_exists('imagecreatefromstring') || !is_file($source) || !is_file($layer)) {
            return new WP_Error('image_processing_unavailable', __('Image processing or the configured overlay is unavailable.', 'h3-mgmt'));
        }
        $photo = @imagecreatefromstring(file_get_contents($source));
        $overlay = @imagecreatefrompng($layer);
        if (!$photo || !$overlay) { return new WP_Error('invalid_image', __('Invalid image.', 'h3-mgmt')); }
        $factor = min(imagesx($photo) / imagesx($overlay), imagesx($photo) / imagesy($overlay));
        $width = max(1, (int) round(imagesx($overlay) * $factor));
        $height = max(1, (int) round(imagesy($overlay) * $factor));
        imagealphablending($photo, true);
        imagecopyresampled($photo, $overlay, imagesx($photo)-$width, imagesy($photo)-$height, 0, 0, $width, $height, imagesx($overlay), imagesy($overlay));
        ob_start();
        try { imagepng($photo); $bytes = ob_get_contents(); }
        finally { ob_end_clean(); unset($photo, $overlay); }
        $result = wp_upload_bits(wp_generate_uuid4() . '.png', null, $bytes);
        return !empty($result['error']) ? new WP_Error('upload_failed', __('Upload failed.', 'h3-mgmt')) : $result + ['type'=>'image/png'];
    }

    public static function may_post(int $race): bool {
        return \Cor\Mgmt\WordPress\TickerBoundary::manager()->native()
            && \Cor\Mgmt\WordPress\TickerBoundary::allowed(get_current_user_id(), $race, 'write');
    }

    public static function ticker_upload(int $race): int {
        if (!self::may_post($race)) { throw new RuntimeException('forbidden_ticker_upload'); }
        $file = H3_MGMT_Security::upload('photo');
        if (is_wp_error($file)) { throw new RuntimeException('invalid_ticker_image'); }
        $id = self::attach($file, $race);
        if (!$id) { wp_delete_file($file['file']); throw new RuntimeException('ticker_image_registration_failed'); }
        return $id;
    }

    public static function ajax_upload(): void {
        $race = H3_MGMT_Security::id($_POST['race'] ?? 0);
        if (!H3_MGMT_Security::post('cor-mgmt-ticker-' . $race) || !self::may_post($race)) {
            wp_send_json_error(['message'=>__('Upload not permitted.', 'h3-mgmt')], 403);
        }
        $result = H3_MGMT_Security::upload('photo');
        if (is_wp_error($result)) { wp_send_json_error(['message'=>$result->get_error_message()], 400); }
        $id = self::attach($result, $race);
        if (!$id) { wp_send_json_error(['message'=>__('Image registration failed.', 'h3-mgmt')], 500); }
        wp_send_json_success(['id'=>$id, 'url'=>self::legacy_url($result['url'])]);
    }

    /** Rotation creates a distinct attachment; shared originals are never overwritten. */
    public static function rotated_copy(int $attachment, int $race, int $angle): int {
        if (!self::may_post($race) || !in_array($angle, [90,180,270], true)
            || !\Cor\Mgmt\WordPress\TickerBoundary::allowed(get_current_user_id(), $race, 'attachment:' . $attachment)) {
            throw new RuntimeException('forbidden_ticker_rotation');
        }
        $path = self::local_path((string) get_attached_file($attachment));
        $editor = wp_get_image_editor($path);
        if (is_wp_error($editor) || is_wp_error($editor->rotate($angle))) { throw new RuntimeException('ticker_rotation_unavailable'); }
        $uploads = wp_upload_dir();
        $name = wp_unique_filename($uploads['path'], 'cor-ticker-' . wp_generate_uuid4() . '.' . pathinfo($path, PATHINFO_EXTENSION));
        $result = $editor->save(trailingslashit($uploads['path']) . $name);
        if (is_wp_error($result)) { throw new RuntimeException('ticker_rotation_failed'); }
        $id = self::attach(['file'=>$result['path'], 'url'=>trailingslashit($uploads['url']) . basename($result['path']), 'type'=>$result['mime-type']], $race);
        if (!$id) { wp_delete_file($result['path']); throw new RuntimeException('ticker_image_registration_failed'); }
        return $id;
    }

    private static function attach(array $file, int $race): int {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $id = wp_insert_attachment(['post_mime_type'=>$file['type'], 'post_title'=>sanitize_file_name(basename($file['file'])),
            'post_status'=>'inherit', 'post_author'=>get_current_user_id()], $file['file'], 0, true);
        if (is_wp_error($id)) { return 0; }
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file['file']));
        update_post_meta($id, '_cor_mgmt_race', $race);
        return (int) $id;
    }

    public static function legacy_url(string $url): string {
        $base = trailingslashit(site_url());
        return str_starts_with($url, $base) ? substr($url, strlen($base)) : $url;
    }

    /** Resolve historical image references only inside the uploads directory. */
    public static function local_path(string $value): string {
        if (str_contains($value, "\0") || str_contains($value, '..')) { return ''; }
        $uploads = wp_upload_dir();
        $base = realpath($uploads['basedir']);
        if (!$base) { return ''; }
        $normalized = wp_normalize_path($value);
        $marker = 'wp-content/uploads/';
        $position = strpos($normalized, $marker);
        if ($position !== false) { $normalized = $base . DIRECTORY_SEPARATOR . substr($normalized, $position + strlen($marker)); }
        // Reject unknown absolute/UNC paths before realpath can touch an external filesystem.
        $root = trailingslashit(wp_normalize_path($base));
        $normalized = wp_normalize_path($normalized);
        $inside = DIRECTORY_SEPARATOR === '\\' ? strncasecmp($normalized, $root, strlen($root)) === 0 : str_starts_with($normalized, $root);
        if (!$inside) { return ''; }
        $path = realpath($normalized);
        return $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path) ? $path : '';
    }

    public static function gateway_image(string $bytes, int $race) {
        $image = @getimagesizefromstring($bytes);
        $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if (strlen($bytes) > 5 * 1024 * 1024 || !$image || !isset($types[$image['mime']]) || $image[0] * $image[1] > 24000000) {
            return new WP_Error('invalid_image', 'Invalid image');
        }
        $file = wp_upload_bits(wp_generate_uuid4() . '.' . $types[$image['mime']], null, $bytes);
        if (!empty($file['error'])) { return new WP_Error('upload_failed', 'Upload failed'); }
        $file['type'] = $image['mime'];
        return self::attach($file, $race) ? self::legacy_url($file['url']) : new WP_Error('upload_failed', 'Upload failed');
    }

    /** Historical paths may predate upload validation: never request executable or active image formats. */
    public static function safe_image_path(string $value): string {
        $path = self::local_path($value);
        if ($path === '' || !in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['jpg','jpeg','jpe','png','gif','webp','bmp','avif'], true)
            || preg_match('/\.(?:php[0-9]*|phtml|pht|phar|cgi|pl|py|asp|aspx|jsp|jspx|shtml|sh)(?:\.|$)/i', basename($path))) { return ''; }
        $image = @getimagesize($path);
        return $image && in_array($image['mime'] ?? '', ['image/jpeg','image/png','image/gif','image/webp','image/bmp','image/x-ms-bmp','image/avif'], true) ? $path : '';
    }

    public static function owned_image(string $value, int $race): int {
        if (!self::may_post($race)) { return 0; }
        $url = preg_match('~^https?://~i', $value) ? $value : site_url('/' . ltrim($value, '/'));
        $id = attachment_url_to_postid($url);
        if (!$id || (int) get_post_meta($id, '_cor_mgmt_race', true) !== $race
            || ((int) get_post_field('post_author', $id) !== get_current_user_id() && !current_user_can('h3_mgmt_edit_teams'))
            || !wp_attachment_is_image($id)) { return 0; }
        $path = realpath((string) get_attached_file($id));
        $uploads = wp_upload_dir();
        $base = realpath($uploads['basedir']);
        return $path && $base && str_starts_with($path, $base . DIRECTORY_SEPARATOR) ? $id : 0;
    }

    /** Retired direct SQL writer; native forms use TickerBoundary and the journaled writer. */
    public static function picture(int $race, bool $rotate = false): string {
        return H3_MGMT_Security::error();
    }
}
