<?php

namespace RRZE\QR;

defined('ABSPATH') || exit;

/**
 * Constructs the plugin services and connects them to WordPress.
 */
class Main
{
    private Settings $settings;
    private QRCode $qrCode;

    public function __construct(string $pluginFile)
    {
        $this->settings = new Settings();
        $this->qrCode = new QRCode($pluginFile, $this->settings);
    }

    public function onLoaded(): void
    {
        add_action('admin_enqueue_scripts', [$this->qrCode, 'enqueueScripts']);
        add_filter('post_row_actions', [$this->qrCode, 'addCreateLink'], 10, 2);
        add_filter('page_row_actions', [$this->qrCode, 'addCreateLink'], 10, 2);
        add_action('admin_menu', [$this->qrCode, 'registerAdminMenu']);
        add_action('admin_init', [$this->settings, 'migrateLegacyColorOption'], 5);
        add_action('admin_init', [$this->qrCode, 'redirectLegacyPage']);
        add_action('wp_ajax_rrze_qr_save_defaults', [$this->settings, 'saveAjaxDefaults']);
    }
}
