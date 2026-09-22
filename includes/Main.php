<?php

namespace RRZE\QR;

defined('ABSPATH') || exit;


/**
 * Hauptklasse (Main)
 */
class Main
{

    protected $pluginFile;

    /**
     * Variablen Werte zuweisen.
     * @param string $pluginFile Pfad- und Dateiname der Plugin-Datei
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;

    }

    /**
     * Es wird ausgeführt, sobald die Klasse instanziiert wird.
     */
    public function onLoaded()
    {
        add_action('admin_enqueue_scripts', [$this, 'rrze_qr_enqueue_scripts']);
        add_filter('post_row_actions', [$this, 'rrze_qr_add_create_link'], 10, 2);
        add_filter('page_row_actions', [$this, 'rrze_qr_add_create_link'], 10, 2);
        add_action('admin_menu', [$this, 'rrze_qr_admin_menu']);
        add_action('admin_init', [$this, 'rrze_qr_migrate_legacy_color_option'], 5);
        add_action('admin_init', [$this, 'rrze_qr_redirect_legacy_page']);
        add_action('wp_ajax_rrze_qr_save_defaults', [$this, 'rrze_qr_ajax_save_defaults']);
    }




    public function rrze_qr_enqueue_scripts($hook)
    {
        if ($hook !== 'toplevel_page_rrze-qr' || !$this->rrze_qr_can_generate()) {
            return;
        }
        $context = $this->rrze_qr_workspace_context();
        $base = dirname($this->pluginFile);
        $asset = require $base . '/assets/js/admin.min.asset.php';
        wp_enqueue_script('rrze-qr-qrious', plugins_url('assets/js/qrious.min.js', $this->pluginFile), [], hash_file('sha256', $base . '/assets/js/qrious.min.js'), true);
        wp_enqueue_script('rrze-qr-admin', plugins_url('assets/js/admin.min.js', $this->pluginFile), array_merge(['rrze-qr-qrious'], $asset['dependencies']), $asset['version'], true);
        wp_enqueue_style('rrze-qr-css', plugins_url('assets/css/rrze-qr.min.css', $this->pluginFile), ['wp-components'], hash_file('sha256', $base . '/assets/css/rrze-qr.min.css'));
        wp_set_script_translations('rrze-qr-admin', 'rrze-qr', $base . '/languages');
        wp_localize_script('rrze-qr-admin', 'rrzeQrAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rrze-qr-nonce'),
            'defaults' => $this->rrze_qr_get_defaults(),
            'initialUrl' => $context['url'] ?? home_url('/'),
            'context' => $context,
            'canSaveDefaults' => current_user_can('manage_options'),
        ]);
    }

    private function rrze_qr_workspace_url($post)
    {
        return add_query_arg(['page' => 'rrze-qr', 'post_id' => $post->ID], admin_url('admin.php'));
    }

    private function rrze_qr_download_filename($post)
    {
        $slug = sanitize_file_name(urldecode($post->post_name));
        return 'qr-code-' . ($slug !== '' ? $slug . '-' : '') . $post->ID . '.png';
    }

    private function rrze_qr_post_title($post)
    {
        return html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES, get_option('blog_charset', 'UTF-8')) ?: __('Untitled', 'rrze-qr');
    }

    private function rrze_qr_workspace_context()
    {
        if (!isset($_GET['post_id'])) {
            return null;
        }
        $raw_id = wp_unslash($_GET['post_id']);
        $id = is_string($raw_id) ? filter_var($raw_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($id === false) {
            wp_die(esc_html__('Invalid post ID.', 'rrze-qr'), '', ['response' => 400]);
        }
        $post = get_post($id);
        if (!$this->rrze_qr_can_generate_for_post($post)) {
            wp_die(esc_html__('You cannot generate a QR code for this post.', 'rrze-qr'), '', ['response' => 403]);
        }
        $url = get_permalink($id);
        if (!$url) {
            wp_die(esc_html__('Could not retrieve permalink.', 'rrze-qr'), '', ['response' => 404]);
        }
        return [
            'url' => $url,
            'title' => $this->rrze_qr_post_title($post),
            'filename' => $this->rrze_qr_download_filename($post),
            'backUrl' => $post->post_type === 'page' ? admin_url('edit.php?post_type=page') : admin_url('edit.php'),
            'backLabel' => $post->post_type === 'page' ? __('Back to pages', 'rrze-qr') : __('Back to posts', 'rrze-qr'),
        ];
    }

    public function rrze_qr_add_create_link($actions, $post)
    {
        if ($this->rrze_qr_can_generate_for_post($post)) {
            $title = $this->rrze_qr_post_title($post);
            $url = esc_url($this->rrze_qr_workspace_url($post));
            /* translators: %s: post or page title. */
            $label = sprintf(__('Create QR code for %s', 'rrze-qr'), $title);
            $actions['create_qr'] = '<a href="' . $url . '" aria-label="' . esc_attr($label) . '">' . esc_html__('Create QR code', 'rrze-qr') . '</a>';
        }
        return $actions;
    }

    private function rrze_qr_can_generate()
    {
        return current_user_can('edit_posts') || current_user_can('edit_pages') || current_user_can('manage_options');
    }

    public function rrze_qr_admin_menu()
    {
        $capability = current_user_can('manage_options') ? 'manage_options' : (current_user_can('edit_pages') ? 'edit_pages' : 'edit_posts');
        $icon = file_get_contents(dirname($this->pluginFile) . '/assets/svg/qr_code_2_24dp_1F1F1F_FILL0_wght400_GRAD0_opsz24.svg');
        add_menu_page(
            __('QR-Codes', 'rrze-qr'),
            __('QR-Codes', 'rrze-qr'),
            $capability,
            'rrze-qr',
            [$this, 'rrze_qr_admin_page'],
            'data:image/svg+xml;base64,' . base64_encode($icon),
            80
        );
    }

    public function rrze_qr_redirect_legacy_page()
    {
        $page = $_GET['page'] ?? '';
        if (in_array($GLOBALS['pagenow'] ?? '', ['tools.php', 'options-general.php'], true)
            && in_array($page, ['rrze-qr', 'rrze-qr-settings'], true)
            && $this->rrze_qr_can_generate()) {
            wp_safe_redirect(admin_url('admin.php?page=rrze-qr'));
            exit;
        }
    }

    public function rrze_qr_admin_page()
    {
        if (!$this->rrze_qr_can_generate()) {
            wp_die(esc_html__('You do not have permission to generate QR codes.', 'rrze-qr'), '', ['response' => 403]);
        }
        $this->rrze_qr_workspace_context();
        ?>
        <div class="wrap rrze-qr-workspace">
            <h1><?php esc_html_e('QR-Codes', 'rrze-qr'); ?></h1>
            <p class="rrze-qr-intro"><?php esc_html_e('Create a QR code, adjust its appearance, and download it as a PNG.', 'rrze-qr'); ?></p>
            <div id="rrze-qr-app"></div>
            <noscript><p><?php esc_html_e('Enable JavaScript to use the QR code generator.', 'rrze-qr'); ?></p></noscript>
        </div>
        <?php
    }

    /**
     * Accept opaque hex colors and the original preset tokens.
     */
    private function rrze_qr_normalize_color($value, $allow_transparent = false)
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        if ($allow_transparent && $value === 'transparent') {
            return $value;
        }
        $legacy = ['white' => '#ffffff', 'black' => '#000000', 'fau' => '#04316a'];
        if (isset($legacy[$value])) {
            return $legacy[$value];
        }
        if (!preg_match('/\A#(?:[0-9a-f]{3}|[0-9a-f]{6})\z/', $value)) {
            return null;
        }
        if (strlen($value) === 4) {
            return '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
        }
        return $value;
    }

    public function rrze_qr_sanitize_foreground($value)
    {
        return $this->rrze_qr_normalize_color($value) ?? '#000000';
    }

    public function rrze_qr_sanitize_background($value)
    {
        return $this->rrze_qr_normalize_color($value, true) ?? '#ffffff';
    }

    private function rrze_qr_valid_color_pair(array $tokens)
    {
        return $tokens['foreground'] !== $tokens['background'];
    }

    /**
     * Einmalige Migration alter Preset-Option rrze_qr_color → rrze_qr_foreground / rrze_qr_background.
     */
    public function rrze_qr_migrate_legacy_color_option()
    {
        if (get_option('rrze_qr_color_legacy_migrated')) {
            return;
        }

        $old = get_option('rrze_qr_color');
        if ($old === false || $old === '') {
            update_option('rrze_qr_color_legacy_migrated', '1');
            return;
        }

        $legacySingle = ['black' => 'black_on_white', '#036' => 'fau_on_white'];
        if (isset($legacySingle[$old])) {
            $old = $legacySingle[$old];
        }

        $schemeMap = [
            'black_on_white' => ['black', 'white'],
            'white_on_black' => ['white', 'black'],
            'fau_on_white' => ['fau', 'white'],
            'white_on_fau' => ['white', 'fau'],
            'black_on_transparent' => ['black', 'transparent'],
            'white_on_transparent' => ['white', 'transparent'],
            'fau_on_transparent' => ['fau', 'transparent'],
            'white_on_fau_transparent' => ['white', 'transparent'],
        ];

        $pair = isset($schemeMap[$old]) ? $schemeMap[$old] : $schemeMap['black_on_white'];
        update_option('rrze_qr_foreground', $pair[0]);
        update_option('rrze_qr_background', $pair[1]);
        delete_option('rrze_qr_color');
        update_option('rrze_qr_color_legacy_migrated', '1');
    }

    private function rrze_qr_normalize_size($value)
    {
        if (!is_int($value) && (!is_string($value) || !preg_match('/\A[0-9]+\z/', $value))) {
            return null;
        }
        return $value >= 128 && $value <= 4096 ? (int) $value : null;
    }

    /**
     * Liest gespeicherte Modus-Farben (mit Fallback nach Migration).
     *
     * @return array{foreground: string, background: string}
     */
    private function rrze_qr_get_defaults()
    {
        $stored = get_option('rrze_qr_defaults', []);
        $stored = is_array($stored) ? $stored : [];
        $tokens = [
            'foreground' => $this->rrze_qr_sanitize_foreground($stored['foreground'] ?? get_option('rrze_qr_foreground', 'black')),
            'background' => $this->rrze_qr_sanitize_background($stored['background'] ?? get_option('rrze_qr_background', 'white')),
        ];
        if (!$this->rrze_qr_valid_color_pair($tokens)) {
            $tokens = ['foreground' => '#000000', 'background' => '#ffffff'];
        }
        $tokens['size'] = $this->rrze_qr_normalize_size($stored['size'] ?? null) ?? 300;
        return $tokens;
    }

    public function rrze_qr_ajax_save_defaults()
    {
        check_ajax_referer('rrze-qr-nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Only administrators can save site defaults.', 'rrze-qr'), 403);
        }
        $fg = $this->rrze_qr_normalize_color(isset($_POST['foreground']) ? wp_unslash($_POST['foreground']) : null);
        $bg = $this->rrze_qr_normalize_color(isset($_POST['background']) ? wp_unslash($_POST['background']) : null, true);
        $size = $this->rrze_qr_normalize_size($_POST['size'] ?? null);
        if ($size === null) {
            wp_send_json_error(__('Enter a whole number between 128 and 4096 pixels.', 'rrze-qr'), 400);
        }
        if ($fg === null || $bg === null) {
            wp_send_json_error(__('Choose valid colors and an export size.', 'rrze-qr'), 400);
        }
        $defaults = ['foreground' => $fg, 'background' => $bg, 'size' => (int) $size];
        if (!$this->rrze_qr_valid_color_pair(['foreground' => $fg, 'background' => $bg])) {
            wp_send_json_error(__('Foreground and background must be different colors.', 'rrze-qr'), 400);
        }
        update_option('rrze_qr_defaults', $defaults);
        if (get_option('rrze_qr_defaults') !== $defaults) {
            wp_send_json_error(__('The defaults could not be saved. Please try again.', 'rrze-qr'), 500);
        }
        wp_send_json_success($defaults);
    }

    private function rrze_qr_can_generate_for_post($post)
    {
        return $this->rrze_qr_can_generate()
            && $post instanceof \WP_Post
            && $post->post_status === 'publish'
            && in_array($post->post_type, ['post', 'page'], true)
            && current_user_can('edit_post', $post->ID);
    }

}
