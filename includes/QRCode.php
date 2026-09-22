<?php

namespace RRZE\QR;

defined('ABSPATH') || exit;

/**
 * Admin workspace, contextual row links, and QR generation assets.
 */
class QRCode
{
    public function __construct(
        private string $pluginFile,
        private Settings $settings
    ) {
    }

    /**
     * Enqueues the required Scripts for QR-Code generation and execution
     */
    public function enqueueScripts($hook): void
    {
        if ($hook !== 'toplevel_page_rrze-qr' || !$this->canGenerate()) {
            return;
        }
        $context = $this->getWorkspaceContext();
        $base = dirname($this->pluginFile);
        $asset = require $base . '/assets/js/admin.min.asset.php';
        wp_enqueue_script('rrze-qr-qrious', plugins_url('assets/js/qrious.min.js', $this->pluginFile), [], hash_file('sha256', $base . '/assets/js/qrious.min.js'), true);
        wp_enqueue_script('rrze-qr-admin', plugins_url('assets/js/admin.min.js', $this->pluginFile), array_merge(['rrze-qr-qrious'], $asset['dependencies']), $asset['version'], true);
        wp_enqueue_style('rrze-qr-css', plugins_url('assets/css/rrze-qr.min.css', $this->pluginFile), ['wp-components'], hash_file('sha256', $base . '/assets/css/rrze-qr.min.css'));
        wp_set_script_translations('rrze-qr-admin', 'rrze-qr', $base . '/languages');
        wp_localize_script('rrze-qr-admin', 'rrzeQrAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rrze-qr-nonce'),
            'defaults' => $this->settings->getDefaults(),
            'initialUrl' => $context['url'] ?? home_url('/'),
            'context' => $context,
            'canSaveDefaults' => current_user_can('manage_options'),
        ]);
    }

    /**
     * Checks publication status and editing permissions for a contextual QR code.
     * @param $post
     *
     * @return bool
     */
    private function canGenerateForPost($post): bool
    {
        return $this->canGenerate()
            && $post instanceof \WP_Post
            && $post->post_status === 'publish'
            && in_array($post->post_type, ['post', 'page'], true)
            && current_user_can('edit_post', $post->ID);
    }

    private function getWorkspaceUrl(\WP_Post $post): string
    {
        return add_query_arg(['page' => 'rrze-qr', 'post_id' => $post->ID], admin_url('admin.php'));
    }

    private function getDownloadFilename(\WP_Post $post): string
    {
        $slug = sanitize_file_name(urldecode($post->post_name));
        return 'qr-code-' . ($slug !== '' ? $slug . '-' : '') . $post->ID . '.png';
    }

    private function getPostTitle(\WP_Post $post): string
    {
        return html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES, get_option('blog_charset', 'UTF-8')) ?: __('Untitled', 'rrze-qr');
    }

    private function getWorkspaceContext(): ?array
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
        if (!$this->canGenerateForPost($post)) {
            wp_die(esc_html__('You cannot generate a QR code for this post.', 'rrze-qr'), '', ['response' => 403]);
        }
        $url = get_permalink($id);
        if (!$url) {
            wp_die(esc_html__('Could not retrieve permalink.', 'rrze-qr'), '', ['response' => 404]);
        }
        return [
            'url' => $url,
            'title' => $this->getPostTitle($post),
            'filename' => $this->getDownloadFilename($post),
            'backUrl' => $post->post_type === 'page' ? admin_url('edit.php?post_type=page') : admin_url('edit.php'),
            'backLabel' => $post->post_type === 'page' ? __('Back to pages', 'rrze-qr') : __('Back to posts', 'rrze-qr'),
        ];
    }

    public function addCreateLink(array $actions, $post): array
    {
        if ($this->canGenerateForPost($post)) {
            $title = $this->getPostTitle($post);
            $url = esc_url($this->getWorkspaceUrl($post));
            /* translators: %s: post or page title. */
            $label = sprintf(__('Create QR code for %s', 'rrze-qr'), $title);
            $actions['create_qr'] = '<a href="' . $url . '" aria-label="' . esc_attr($label) . '">' . esc_html__('Create QR code', 'rrze-qr') . '</a>';
        }
        return $actions;
    }

    private function canGenerate(): bool
    {
        return current_user_can('edit_posts') || current_user_can('edit_pages') || current_user_can('manage_options');
    }

    public function registerAdminMenu(): void
    {
        $capability = current_user_can('manage_options') ? 'manage_options' : (current_user_can('edit_pages') ? 'edit_pages' : 'edit_posts');
        $icon = file_get_contents(dirname($this->pluginFile) . '/assets/svg/qr_code_2_24dp_1F1F1F_FILL0_wght400_GRAD0_opsz24.svg');
        add_menu_page(
            __('QR-Codes', 'rrze-qr'),
            __('QR-Codes', 'rrze-qr'),
            $capability,
            'rrze-qr',
            [$this, 'renderAdminPage'],
            'data:image/svg+xml;base64,' . base64_encode($icon),
            80
        );
    }

    public function redirectLegacyPage(): void
    {
        $page = $_GET['page'] ?? '';
        if (in_array($GLOBALS['pagenow'] ?? '', ['tools.php', 'options-general.php'], true)
            && in_array($page, ['rrze-qr', 'rrze-qr-settings'], true)
            && $this->canGenerate()) {
            wp_safe_redirect(admin_url('admin.php?page=rrze-qr'));
            exit;
        }
    }

    public function renderAdminPage(): void
    {
        if (!$this->canGenerate()) {
            wp_die(esc_html__('You do not have permission to generate QR codes.', 'rrze-qr'), '', ['response' => 403]);
        }
        $this->getWorkspaceContext();

        $html = '<div class="wrap rrze-qr-workspace">';
        $html .= '<h1>' . esc_html__('QR-Codes', 'rrze-qr') . '</h1>';
        $html .= '<p class="rrze-qr-intro">' . esc_html__('Create a QR code, adjust its appearance, and download it as a PNG.', 'rrze-qr') . '</p>';
        $html .= '<div id="rrze-qr-app"></div>';
        $html .= '<noscript><p>' . esc_html__('Enable JavaScript to use the QR code generator.', 'rrze-qr') . '</p></noscript>';
        $html .= '</div>';
        echo $html;
    }
}
