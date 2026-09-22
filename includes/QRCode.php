<?php

namesparce RRZE\QR;

defined('ABSPATH') || exit;

/**
 * Plugin for initializing, hanadling and executing QR-Code generation
 */
class QRCode
{
    protected $pluginFile;

    /**
     * Constructor
     * @param string $pluginFile Path- and File-Shorthands
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Acitvates the required Scripts and Functions
     * @return void
     */
    public function bootstrap(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    /**
     * Enqueues the required Scripts for QR-Code generation and execution
     */
    public function enqueueScripts($hook): void
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

    /**
     * Checks if the QRCode generation feature should be available for this post.
     * @param $post
     *
     * @return bool
     */
    private function isQRCodeAvailableForThisPost($post): bool
    {
        return $this->rrze_qr_can_generate()
            && $post instanceof \WP_Post
            && $post->post_status === 'publish'
            && in_array($post->post_type, ['post', 'page'], true)
            && current_user_can('edit_post', $post->ID);
    }
}