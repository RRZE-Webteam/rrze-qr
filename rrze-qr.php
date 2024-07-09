<?php

/*
Plugin Name:     RRZE QR
Plugin URI:      https://gitlab.rrze.fau.de/rrze-webteam/rrze-qr
Description:     Plugin, um QR Codes zu generieren 
Version:         0.0.2
Requires at least: 6.4
Requires PHP:      8.2
Author:          RRZE Webteam
Author URI:      https://blogs.fau.de/webworking/
License:         GNU General Public License v2
License URI:     http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:     /languages
Text Domain:     rrze-qr
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Enqueue QRious library and custom scripts
function rrze_qr_enqueue_scripts($hook) {
    // Only load scripts on appropriate admin pages
    if ($hook === 'edit.php' || $hook === 'edit-page.php' || $hook === 'toplevel_page_rrze-qr') {
        wp_enqueue_script('qrious', plugin_dir_url(__FILE__) . 'src/js/qrious.min.js', array(), null, true);
        wp_enqueue_script('rrze-qr-generator', plugin_dir_url(__FILE__) . 'src/js/rrze-qr.js', array('qrious'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'rrze_qr_enqueue_scripts');

// Add "QR generieren" link to posts and pages list
function rrze_qr_add_generate_link($actions, $post) {
    if ($post->post_status === 'publish') {
        $actions['generate_qr'] = '<a href="#" class="generate-qr" data-id="' . $post->ID . '">QR generieren</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'rrze_qr_add_generate_link', 10, 2);
add_filter('page_row_actions', 'rrze_qr_add_generate_link', 10, 2);

// Add admin menu entry
function rrze_qr_admin_menu() {
    add_menu_page('QR generieren', 'QR generieren', 'manage_options', 'rrze-qr', 'rrze_qr_settings_page');
}
add_action('admin_menu', 'rrze_qr_admin_menu');

// Admin settings page content
function rrze_qr_settings_page() {
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

// Handle AJAX request for generating QR code
function rrze_qr_generate() {
    check_ajax_referer('rrze-qr-nonce', 'nonce');

    $url = esc_url_raw($_POST['url']);
    $response_code = wp_remote_retrieve_response_code(wp_remote_head($url));

    if ($response_code >= 200 && $response_code < 300) {
        wp_send_json_success($url);
    } else {
        wp_send_json_error('URL does not exist or returned an error.');
    }
}
add_action('wp_ajax_rrze_qr_generate', 'rrze_qr_generate');


function rrze_qr_localize_script() {
    wp_localize_script('rrze-qr-generator', 'rrzeQr', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rrze-qr-nonce')
    ));
}
add_action('admin_enqueue_scripts', 'rrze_qr_localize_script');
