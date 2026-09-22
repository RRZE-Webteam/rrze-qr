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
     * Constructor
     * @param string $pluginFile Pfad- und Dateiname der Plugin-Datei
     */
    public function __construct($pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    /**
     * Loader to load requried QR-Code Scripts and register Admin panels, page row actions and Ajax scripts
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

    /**
     * Enqueues required QR-Code Scripts.
     * @param $hook
     *
     * @return void
     */
    public function rrze_qr_enqueue_scripts($hook)
    {

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
}
