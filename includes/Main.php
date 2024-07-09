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
        add_action('wp_ajax_rrze_qr_get_permalink', [$this, 'rrze_qr_get_permalink']);
        add_action('admin_enqueue_scripts', [$this, 'rrze_qr_localize_script']);
    }




    // Enqueue QRious library and custom scripts
    public function rrze_qr_enqueue_scripts($hook)
    {
        // Only load scripts on appropriate admin pages
        if ($hook === 'edit.php' || $hook === 'edit-page.php' || $hook === 'tools_page_rrze-qr') {
            wp_enqueue_script('qrious', plugins_url('assets/js/qrious.min.js', plugin_basename($this->pluginFile)), array('jquery'), null, true);
            wp_enqueue_script('rrze-qr-js', plugins_url('assets/js/rrze-qr.min.js', plugin_basename($this->pluginFile)), array('jquery', 'qrious'), null, true);
            wp_enqueue_style('rrze-qr-css', plugins_url('assets/css/rrze-qr.min.css', plugin_basename($this->pluginFile)));
        }
    }

    // Add "Download QR" link to posts and pages list
    public function rrze_qr_add_download_link($actions, $post)
    {
        if ($post->post_status === 'publish') {
            $actions['download_qr'] = '<a href="#" class="download-qr" data-id="' . $post->ID . '">Download QR</a>';
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
            [$this, 'rrze_qr_settings_page'] // Callback function
        );
    }

    // Admin settings page content
    public function rrze_qr_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>QR Code Generator</h1>
            <form id="rrze-qr-form">
                <label for="rrze-qr-url">URL:</label>
                <input type="url" id="rrze-qr-url" name="rrze-qr-url" required>
                <button type="submit" class="button button-primary">Generate QR Code</button>
            </form>
            <canvas id="rrze-qr-canvas" style="display:none;"></canvas>
            <a id="rrze-qr-download" style="display:none;" download="qr-code.png">Download QR Code</a>
        </div>
        <?php
    }

    // Handle AJAX request to get permalink
    public function rrze_qr_get_permalink()
    {
        check_ajax_referer('rrze-qr-nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $permalink = get_permalink($post_id);

        if ($permalink) {
            wp_send_json_success($permalink);
        } else {
            wp_send_json_error('Could not retrieve permalink.');
        }
    }

    // Localize script for AJAX
    public function rrze_qr_localize_script()
    {
        wp_localize_script('rrze-qr-js', 'rrzeQr', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rrze-qr-nonce')
        )
        );
    }
}