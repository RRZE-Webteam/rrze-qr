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
        add_filter('post_row_actions', [$this, 'rrze_qr_add_download_link'], 10, 2);
        add_filter('page_row_actions', [$this, 'rrze_qr_add_download_link'], 10, 2);
        add_action('admin_menu', [$this, 'rrze_qr_admin_menu']);
        add_action('admin_init', [$this, 'rrze_qr_migrate_legacy_color_option'], 5);
        add_action('admin_init', [$this, 'rrze_qr_redirect_legacy_page']);
        add_action('wp_ajax_rrze_qr_get_permalink', [$this, 'rrze_qr_get_permalink']);
        add_action('wp_ajax_rrze_qr_save_defaults', [$this, 'rrze_qr_ajax_save_defaults']);
    }




    public function rrze_qr_enqueue_scripts($hook)
    {
        $workspace = $hook === 'toplevel_page_rrze-qr';
        if (!$workspace && $hook !== 'edit.php') {
            return;
        }
        if ($workspace && !$this->rrze_qr_can_generate()) {
            return;
        }
        $base = dirname($this->pluginFile);
        $name = $workspace ? 'admin' : 'rrze-qr';
        $handle = $workspace ? 'rrze-qr-admin' : 'rrze-qr-js';
        $asset = require $base . '/assets/js/' . $name . '.min.asset.php';
        wp_enqueue_script('rrze-qr-qrious', plugins_url('assets/js/qrious.min.js', $this->pluginFile), [], hash_file('sha256', $base . '/assets/js/qrious.min.js'), true);
        wp_enqueue_script($handle, plugins_url('assets/js/' . $name . '.min.js', $this->pluginFile), array_merge(['rrze-qr-qrious'], $workspace ? $asset['dependencies'] : array_merge(['jquery'], $asset['dependencies'])), $asset['version'], true);
        wp_enqueue_style('rrze-qr-css', plugins_url('assets/css/rrze-qr.min.css', $this->pluginFile), $workspace ? ['wp-components'] : [], hash_file('sha256', $base . '/assets/css/rrze-qr.min.css'));
        if ($workspace) {
            wp_set_script_translations($handle, 'rrze-qr', $base . '/languages');
            wp_localize_script($handle, 'rrzeQrAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rrze-qr-nonce'),
                'defaults' => $this->rrze_qr_get_defaults(),
                'initialUrl' => home_url('/'),
                'canSaveDefaults' => current_user_can('manage_options'),
            ]);
        } else {
            $this->rrze_qr_localize_script();
        }
    }

    // Add "Download QR" link to posts and pages list
    public function rrze_qr_add_download_link($actions, $post)
    {
        if ($this->rrze_qr_can_download($post)) {
            $actions['download_qr'] = '<a href="#" class="download-qr" data-id="' . esc_attr($post->ID) . '">' . esc_html__('Download QR', 'rrze-qr') . '</a>';
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
        $tokens['size'] = in_array($stored['size'] ?? null, [300, 600, 1200], true) ? $stored['size'] : 300;
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
        $size = $_POST['size'] ?? null;
        if ($fg === null || $bg === null
            || !in_array($size, ['300', '600', '1200'], true)) {
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

    /**
     * Farbwerte für QRious aus Vorder-/Hintergrund-Tokens (bereits sanitisiert).
     *
     * @param array{foreground: string, background: string} $tokens
     * @return array{foreground: string, background: string, backgroundAlpha: int}
     */
    private function rrze_qr_colors_for_qrious_from_tokens(array $tokens)
    {
        return [
            'foreground' => $tokens['foreground'],
            'background' => $tokens['background'] === 'transparent' ? '#ffffff' : $tokens['background'],
            'backgroundAlpha' => $tokens['background'] === 'transparent' ? 0 : 1,
        ];
    }

    /**
     * Farbwerte für QRious (gespeicherte Einstellung).
     *
     * @return array{foreground: string, background: string, backgroundAlpha: int}
     */
    private function rrze_qr_colors_for_qrious()
    {
        return $this->rrze_qr_colors_for_qrious_from_tokens($this->rrze_qr_get_defaults());
    }

    // Handle AJAX request to get permalink
    public function rrze_qr_get_permalink()
    {
        check_ajax_referer('rrze-qr-nonce', 'nonce');

        $raw_id = isset($_POST['post_id']) ? wp_unslash($_POST['post_id']) : null;
        $post_id = is_string($raw_id) ? filter_var($raw_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        if ($post_id === false) {
            wp_send_json_error(__('Invalid post ID.', 'rrze-qr'), 400);
        }
        $post = get_post($post_id);
        if (!$this->rrze_qr_can_download($post)) {
            wp_send_json_error(__('You cannot generate a QR code for this post.', 'rrze-qr'), 403);
        }
        $permalink = get_permalink($post_id);

        if ($permalink) {
            wp_send_json_success(['url' => $permalink, 'colors' => $this->rrze_qr_colors_for_qrious(), 'size' => $this->rrze_qr_get_defaults()['size']]);
        } else {
            wp_send_json_error(__('Could not retrieve permalink.', 'rrze-qr'), 404);
        }
    }

    private function rrze_qr_can_download($post)
    {
        return $post instanceof \WP_Post
            && $post->post_status === 'publish'
            && in_array($post->post_type, ['post', 'page'], true)
            && current_user_can('edit_post', $post->ID);
    }

    // Localize script for AJAX
    public function rrze_qr_localize_script()
    {
        wp_localize_script(
            'rrze-qr-js',
            'rrzeQr',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rrze-qr-nonce'),
                'strings' => [
                    'invalidUrl' => __('Enter a valid HTTP or HTTPS URL.', 'rrze-qr'),
                    'tooLong' => __('This URL is too long for a QR code. Use a shorter URL (maximum 2,953 encoded characters).', 'rrze-qr'),
                    'requestFailed' => __('The request failed. Reload the page and try again.', 'rrze-qr'),
                    'generationFailed' => __('The QR code could not be generated. Reload the page and try again.', 'rrze-qr'),
                    'generating' => __('Generating QR code…', 'rrze-qr'),
                    'downloadStarted' => __('QR code download started.', 'rrze-qr'),
                ],
            ]
        );
    }
}
