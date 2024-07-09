<?php

/*
Plugin Name:     RRZE QR
Plugin URI:      https://gitlab.rrze.fau.de/rrze-webteam/rrze-qr
Description:     Plugin, um QR Codes zu generieren 
Version:         0.0.5
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
     if ($hook === 'edit.php' || $hook === 'edit-page.php' || $hook === 'tools_page_rrze-qr') {
         wp_enqueue_script('qrious', plugin_dir_url(__FILE__) . 'src/js/qrious.min.js', array(), null, true);
         wp_enqueue_script('rrze-qr-js', plugin_dir_url(__FILE__) . 'src/js/rrze-qr.js', array('jquery', 'qrious'), null, true);
     }
 }
 add_action('admin_enqueue_scripts', 'rrze_qr_enqueue_scripts');
 
 // Add "Download QR" link to posts and pages list
 function rrze_qr_add_download_link($actions, $post) {
     if ($post->post_status === 'publish') {
         $actions['download_qr'] = '<a href="#" class="download-qr" data-id="' . $post->ID . '">Download QR</a>';
     }
     return $actions;
 }
 add_filter('post_row_actions', 'rrze_qr_add_download_link', 10, 2);
 add_filter('page_row_actions', 'rrze_qr_add_download_link', 10, 2);
 
// Add admin menu entry
function rrze_qr_admin_menu() {
    add_submenu_page(
        'tools.php',            // Parent slug
        'QR Code generieren',   // Page title
        'QR Code generieren',   // Menu title
        'manage_options',       // Capability
        'rrze-qr',              // Menu slug
        'rrze_qr_settings_page' // Callback function
    );
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
 
// Handle AJAX request to get permalink
function rrze_qr_get_permalink() {
    check_ajax_referer('rrze-qr-nonce', 'nonce');

    $post_id = intval($_POST['post_id']);
    $permalink = get_permalink($post_id);

    if ($permalink) {
        wp_send_json_success($permalink);
    } else {
        wp_send_json_error('Could not retrieve permalink.');
    }
}
add_action('wp_ajax_rrze_qr_get_permalink', 'rrze_qr_get_permalink');
 
 // Localize script for AJAX
 function rrze_qr_localize_script() {
     wp_localize_script('rrze-qr-js', 'rrzeQr', array(
         'ajaxurl' => admin_url('admin-ajax.php'),
         'nonce' => wp_create_nonce('rrze-qr-nonce')
     ));
 }
 add_action('admin_enqueue_scripts', 'rrze_qr_localize_script');
 