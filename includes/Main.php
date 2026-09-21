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
        add_action('admin_init', [$this, 'rrze_qr_register_settings']);
        add_action('wp_ajax_rrze_qr_get_permalink', [$this, 'rrze_qr_get_permalink']);
        add_action('wp_ajax_rrze_qr_get_colors', [$this, 'rrze_qr_ajax_get_colors']);
        add_action('wp_ajax_rrze_qr_resolve_colors', [$this, 'rrze_qr_ajax_resolve_colors']);
        add_action('admin_enqueue_scripts', [$this, 'rrze_qr_localize_script']);
    }




    // Enqueue QRious library and custom scripts
    public function rrze_qr_enqueue_scripts($hook)
    {
        // Only load scripts on appropriate admin pages
        if ($hook === 'edit.php' || $hook === 'edit-page.php' || $hook === 'tools_page_rrze-qr' || $hook === 'settings_page_rrze-qr-settings') {
            wp_enqueue_script('qrious', plugins_url('assets/js/qrious.min.js', plugin_basename($this->pluginFile)), array('jquery'), null, true);
            wp_enqueue_script('rrze-qr-js', plugins_url('assets/js/rrze-qr.min.js', plugin_basename($this->pluginFile)), array('jquery', 'qrious'), null, true);
            wp_enqueue_style('rrze-qr-css', plugins_url('assets/css/rrze-qr.min.css', plugin_basename($this->pluginFile)));
        }
    }

    // Add "Download QR" link to posts and pages list
    public function rrze_qr_add_download_link($actions, $post)
    {
        if ($this->rrze_qr_can_download($post)) {
            $actions['download_qr'] = '<a href="#" class="download-qr" data-id="' . esc_attr($post->ID) . '">Download QR</a>';
        }
        return $actions;
    }

    // Add admin menu entry
    public function rrze_qr_admin_menu()
    {
        add_submenu_page(
            'tools.php',            // Parent slug
            'QR Code generieren',   // Page title
            'QR Code generieren',   // Menu title
            'manage_options',       // Capability
            'rrze-qr',              // Menu slug
            [$this, 'rrze_qr_tools_page'] // Callback function
        );

        add_submenu_page(
            'options-general.php',  // Parent slug
            'RRZE QR',              // Page title
            'RRZE QR',              // Menu title
            'manage_options',       // Capability
            'rrze-qr-settings',     // Menu slug
            [$this, 'rrze_qr_settings_page'] // Callback function
        );
    }


    // Register plugin settings
    public function rrze_qr_register_settings()
    {
        register_setting('rrze_qr_settings_group', 'rrze_qr_foreground', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'rrze_qr_save_foreground'],
            'default' => 'black',
        ]);
        register_setting('rrze_qr_settings_group', 'rrze_qr_background', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'rrze_qr_save_background'],
            'default' => 'white',
        ]);
    }

    /**
     * @return string[]
     */
    private function rrze_qr_foreground_allowed()
    {
        return ['white', 'black', 'fau'];
    }

    /**
     * @return string[]
     */
    private function rrze_qr_background_allowed()
    {
        return ['white', 'black', 'fau', 'transparent'];
    }

    /**
     * @param mixed $value Raw option value from the form/API.
     */
    public function rrze_qr_sanitize_foreground($value)
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($value, $this->rrze_qr_foreground_allowed(), true) ? $value : 'black';
    }

    /**
     * @param mixed $value Raw option value from the form/API.
     */
    public function rrze_qr_sanitize_background($value)
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        return in_array($value, $this->rrze_qr_background_allowed(), true) ? $value : 'white';
    }

    private function rrze_qr_valid_color_pair(array $tokens)
    {
        return $tokens['background'] === 'transparent'
            || ($tokens['foreground'] !== $tokens['background']
                && in_array('white', $tokens, true));
    }

    public function rrze_qr_save_foreground($value)
    {
        $tokens = [
            'foreground' => $this->rrze_qr_sanitize_foreground($value),
            'background' => $this->rrze_qr_sanitize_background(wp_unslash($_POST['rrze_qr_background'] ?? get_option('rrze_qr_background', 'white'))),
        ];
        if (!$this->rrze_qr_valid_color_pair($tokens)) {
            add_settings_error('rrze_qr_settings_group', 'rrze_qr_contrast', __('Choose contrasting colors. Black on white has been used instead.', 'rrze-qr'));
            return 'black';
        }
        return $tokens['foreground'];
    }

    public function rrze_qr_save_background($value)
    {
        $tokens = [
            'foreground' => $this->rrze_qr_sanitize_foreground(wp_unslash($_POST['rrze_qr_foreground'] ?? get_option('rrze_qr_foreground', 'black'))),
            'background' => $this->rrze_qr_sanitize_background($value),
        ];
        return $this->rrze_qr_valid_color_pair($tokens) ? $tokens['background'] : 'white';
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
    private function rrze_qr_get_fg_bg_tokens()
    {
        $fg = get_option('rrze_qr_foreground', 'black');
        $bg = get_option('rrze_qr_background', 'white');
        $tokens = [
            'foreground' => $this->rrze_qr_sanitize_foreground($fg),
            'background' => $this->rrze_qr_sanitize_background($bg),
        ];
        return $this->rrze_qr_valid_color_pair($tokens) ? $tokens : ['foreground' => 'black', 'background' => 'white'];
    }

    /**
     * Farbwerte für QRious aus Vorder-/Hintergrund-Tokens (bereits sanitisiert).
     *
     * @param array{foreground: string, background: string} $tokens
     * @return array{foreground: string, background: string, backgroundAlpha: int}
     */
    private function rrze_qr_colors_for_qrious_from_tokens(array $tokens)
    {
        $css = [
            'white' => 'white',
            'black' => 'black',
            'fau' => '#036',
        ];

        $foreground = $css[$tokens['foreground']];

        if ($tokens['background'] === 'transparent') {
            return [
                'foreground' => $foreground,
                'background' => 'white',
                'backgroundAlpha' => 0,
            ];
        }

        return [
            'foreground' => $foreground,
            'background' => $css[$tokens['background']],
            'backgroundAlpha' => 1,
        ];
    }

    /**
     * Farbwerte für QRious (gespeicherte Einstellung).
     *
     * @return array{foreground: string, background: string, backgroundAlpha: int}
     */
    private function rrze_qr_colors_for_qrious()
    {
        return $this->rrze_qr_colors_for_qrious_from_tokens($this->rrze_qr_get_fg_bg_tokens());
    }

    // Tools page content
    public function rrze_qr_tools_page()
    {
        ?>
        <div class="wrap">
            <h1>QR Code Generator</h1>
            <form id="rrze-qr-form">
                <label for="rrze-qr-url">URL:</label>
                <input type="url" id="rrze-qr-url" name="rrze-qr-url" required>
                <button type="submit" class="button button-primary">Generate QR Code</button>
            </form>
            <canvas id="rrze-qr-canvas" class="rrze-qr-canvas rrze-qr--hidden" width="300" height="300"></canvas>
            <a id="rrze-qr-download" class="button button-primary rrze-qr-download-link rrze-qr--hidden" download="qr-code.png" href="#">Download QR Code</a>
        </div>
        <?php
    }

    // Admin settings page content
    public function rrze_qr_settings_page()
    {
        $tokens = $this->rrze_qr_get_fg_bg_tokens();
        $foreground = $tokens['foreground'];
        $background = $tokens['background'];
        ?>
        <div class="wrap">
            <h1>RRZE QR</h1>
            <?php settings_errors('rrze_qr_settings_group'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('rrze_qr_settings_group'); ?>
                <table class="form-table rrze-qr-settings" role="presentation">
                    <tr>
                        <td class="rrze-qr-settings__col rrze-qr-settings__col--first">
                            <p class="rrze-qr-settings__heading"><strong>Vordergrund</strong></p>
                            <fieldset class="rrze-qr-settings__fieldset">
                                <legend class="screen-reader-text">Vordergrund</legend>
                                <label class="rrze-qr-settings__label">
                                    <input type="radio" name="rrze_qr_foreground" value="white" <?php checked($foreground, 'white'); ?>>
                                    Weiß
                                </label>
                                <br>
                                <label class="rrze-qr-settings__label">
                                    <input type="radio" name="rrze_qr_foreground" value="black" <?php checked($foreground, 'black'); ?>>
                                    Schwarz
                                </label>
                                <br>
                                <label class="rrze-qr-settings__label">
                                    <input type="radio" name="rrze_qr_foreground" value="fau" <?php checked($foreground, 'fau'); ?>>
                                    FAU-Blau
                                </label>
                            </fieldset>
                        </td>
                        <td class="rrze-qr-settings__col">
                            <p class="rrze-qr-settings__heading"><strong>Hintergrund</strong></p>
                            <fieldset class="rrze-qr-settings__fieldset">
                                <legend class="screen-reader-text">Hintergrund</legend>
                                <label class="rrze-qr-settings__label">
                                    <input type="radio" name="rrze_qr_background" value="white" <?php checked($background, 'white'); ?>>
                                    Weiß
                                </label>
                                <br>
                                <label class="rrze-qr-settings__label">
                                    <input type="radio" name="rrze_qr_background" value="black" <?php checked($background, 'black'); ?>>
                                    Schwarz
                                </label>
                                <br>
                                <label class="rrze-qr-settings__label">
                                    <input type="radio" name="rrze_qr_background" value="fau" <?php checked($background, 'fau'); ?>>
                                    FAU-Blau
                                </label>
                                <br>
                                <label class="rrze-qr-settings__label">
                                    <input type="radio" name="rrze_qr_background" value="transparent" <?php checked($background, 'transparent'); ?>>
                                    Transparent
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
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
            wp_send_json_success($permalink);
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

    /**
     * Aktuelle gespeicherte Farben (für QR-Erzeugung ohne Admin-Seite neu laden).
     */
    public function rrze_qr_ajax_get_colors()
    {
        check_ajax_referer('rrze-qr-nonce', 'nonce');
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && !current_user_can('manage_options')) {
            wp_send_json_error('', 403);
        }
        wp_send_json_success($this->rrze_qr_colors_for_qrious());
    }

    /**
     * Farben aus Vorder-/Hintergrundwahl (für Live-Vorschau in den Einstellungen).
     */
    public function rrze_qr_ajax_resolve_colors()
    {
        check_ajax_referer('rrze-qr-nonce', 'nonce');
        if (! current_user_can('manage_options')) {
            wp_send_json_error('', 403);
        }
        $fg = isset($_POST['foreground']) ? wp_unslash($_POST['foreground']) : '';
        $bg = isset($_POST['background']) ? wp_unslash($_POST['background']) : '';
        $tokens = [
            'foreground' => $this->rrze_qr_sanitize_foreground($fg),
            'background' => $this->rrze_qr_sanitize_background($bg),
        ];
        if (!$this->rrze_qr_valid_color_pair($tokens)) {
            wp_send_json_error(__('Choose contrasting foreground and background colors.', 'rrze-qr'), 400);
        }
        wp_send_json_success($this->rrze_qr_colors_for_qrious_from_tokens($tokens));
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
                'colors' => $this->rrze_qr_colors_for_qrious(),
                'previewSampleUrl' => home_url('/'),
            ]
        );
    }
}
