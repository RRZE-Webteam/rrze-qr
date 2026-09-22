<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Isolated endpoint regression checks; no WordPress database is modified.
define('ABSPATH', __DIR__ . '/');
class WP_Post
{
    public function __construct(public int $ID, public string $post_status = 'publish', public string $post_type = 'post', public string $post_name = 'contact', public string $post_title = 'Contact') {}
}
class JsonResponse extends RuntimeException
{
    public function __construct(public bool $success, public mixed $data, public int $status) { parent::__construct(); }
}
function __($text, $domain) { return $text; }
function esc_html__($text, $domain) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_html_e($text, $domain) { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain) { echo esc_attr($text); }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function wp_unslash($value) { return $value; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return esc_attr($value); }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function sanitize_file_name($value) { return preg_replace('/[^a-zA-Z0-9_-]/', '', $value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function get_the_title($post) { return $post->post_title; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function plugins_url($path, $file) { return 'https://example.test/plugins/rrze-qr/' . $path; }
function wp_enqueue_script($handle, $url, $dependencies, $version, $footer) { $GLOBALS['assets'][$handle] = compact('dependencies', 'version'); }
function wp_enqueue_style($handle, $url, $dependencies, $version) { $GLOBALS['assets'][$handle] = compact('dependencies', 'version'); }
function wp_localize_script($handle, $name, $data) { $GLOBALS['localized'][$name] = $data; }
function wp_set_script_translations($handle, $domain, $path) { $GLOBALS['translations'][$handle] = $domain; }
function update_option($key, $value) { if (empty($GLOBALS['fail_update'])) { $GLOBALS['options'][$key] = $value; } }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function add_menu_page(...$args) { $GLOBALS['menu'] = $args; }
function wp_die($message, $title, $args) { throw new JsonResponse(false, $message, $args['response']); }
function wp_safe_redirect($url) { throw new JsonResponse(true, $url, 302); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'test'; }
function home_url($path) { return 'https://example.test' . $path; }
function check_ajax_referer($action, $field) {
    if (!$GLOBALS['valid_nonce']) { throw new JsonResponse(false, 'Invalid nonce', 403); }
}
function current_user_can($capability, $id = null) { return $id === null ? !empty($GLOBALS['caps'][$capability]) : ($GLOBALS['allowed'] && $id === 42); }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_permalink($id) { return 'https://example.test/post-' . $id . '/'; }
function wp_send_json_error($data, $status = 200) { throw new JsonResponse(false, $data, $status); }
function wp_send_json_success($data) { throw new JsonResponse(true, $data, 200); }
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
require __DIR__ . '/../includes/Main.php';
$main = new RRZE\QR\Main(__DIR__ . '/../rrze-qr.php');
$GLOBALS['allowed'] = true;
$GLOBALS['valid_nonce'] = true;
$GLOBALS['posts'] = [42 => new WP_Post(42)];
$GLOBALS['caps'] = ['manage_options' => true];
function save_defaults($main, $data): JsonResponse {
    $_POST = $data;
    try { $main->rrze_qr_ajax_save_defaults(); } catch (JsonResponse $response) { return $response; }
    throw new RuntimeException('Save endpoint did not terminate');
}
$original = ['foreground' => 'fau', 'background' => 'white', 'size' => 600];
$GLOBALS['options']['rrze_qr_defaults'] = $original;
foreach (['white', 'black', 'fau'] as $fg) {
    foreach (['white', 'black', 'fau', 'transparent'] as $bg) {
        $valid = $fg !== $bg;
        $before = $GLOBALS['options']['rrze_qr_defaults'];
        $response = save_defaults($main, ['foreground' => $fg, 'background' => $bg, 'size' => '600']);
        check($response->success === $valid, "Unexpected color validation: $fg/$bg");
        if (!$valid) { check($GLOBALS['options']['rrze_qr_defaults'] === $before, 'Invalid defaults must not replace stored values'); }
    }
}
foreach ([[], ['foreground' => ['black'], 'background' => 'white', 'size' => '300'], ['foreground' => 'red', 'background' => 'white', 'size' => '300'], ['foreground' => 'black', 'background' => 'white', 'size' => '99999']] as $invalid) {
    check(save_defaults($main, $invalid)->status === 400, 'Malformed defaults must be rejected');
}
$response = save_defaults($main, ['foreground' => ' #A1b ', 'background' => '#FEDcBa', 'size' => '600']);
$custom = ['foreground' => '#aa11bb', 'background' => '#fedcba', 'size' => 600];
check($response->success && $response->data === $custom, 'Custom colors must be normalized and returned');
check($GLOBALS['options']['rrze_qr_defaults'] === $custom, 'Custom defaults must be persisted');
$main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
check($GLOBALS['localized']['rrzeQrAdmin']['defaults'] === $custom, 'Workspace must restore custom defaults');
foreach (['#12', '#gggggg', '#0008', '#00000080', 'url(x)', 'rgb(0,0,0)', ['#123456']] as $invalid) {
    foreach (['foreground', 'background'] as $field) {
        $input = ['foreground' => '#123456', 'background' => '#ffffff', 'size' => '300'];
        $input[$field] = $invalid;
        check(save_defaults($main, $input)->status === 400, 'Invalid custom colors must be rejected');
        check($GLOBALS['options']['rrze_qr_defaults'] === $custom, 'Invalid custom colors must leave defaults unchanged');
    }
}
check(save_defaults($main, ['foreground' => '#fff', 'background' => 'white', 'size' => '300'])->status === 400, 'Equivalent colors must be rejected after normalization');
check(save_defaults($main, ['foreground' => 'transparent', 'background' => '#fff', 'size' => '300'])->status === 400, 'Foreground must be opaque');
check(save_defaults($main, ['foreground' => '#123456', 'background' => 'transparent', 'size' => '300'])->success, 'Custom foreground supports transparency');
$GLOBALS['caps'] = ['edit_posts' => true];
check(save_defaults($main, ['foreground' => 'black', 'background' => 'white', 'size' => '300'])->status === 403, 'Editors must not save site defaults');
$GLOBALS['caps'] = ['manage_options' => true];
$GLOBALS['valid_nonce'] = false;
check(save_defaults($main, ['foreground' => 'black', 'background' => 'white', 'size' => '300'])->status === 403, 'Saving requires a valid nonce');
$GLOBALS['valid_nonce'] = true;
$GLOBALS['fail_update'] = true;
check(save_defaults($main, ['foreground' => 'black', 'background' => 'white', 'size' => '1200'])->status === 500, 'Storage failures must be reported');
$GLOBALS['fail_update'] = false;
check(save_defaults($main, ['foreground' => 'black', 'background' => 'white', 'size' => '1200'])->success, 'Administrator can save defaults');
echo "PHP defaults checks passed.\n";

$GLOBALS['caps'] = [];
try { $main->rrze_qr_admin_page(); throw new RuntimeException('Unauthorized page access succeeded'); }
catch (JsonResponse $response) { check($response->status === 403, 'Unauthorized users cannot access the workspace'); }
foreach (['edit_posts', 'edit_pages', 'manage_options'] as $capability) {
    $GLOBALS['caps'] = [$capability => true];
    $main->rrze_qr_admin_menu();
    check($GLOBALS['menu'][2] === $capability, 'Menu must support post editors, page editors, and administrators');
    check(str_starts_with($GLOBALS['menu'][5], 'data:image/svg+xml;base64,'), 'Menu should use the provided SVG');
    ob_start(); $main->rrze_qr_admin_page(); $html = ob_get_clean();
    check(str_contains($html, 'id="rrze-qr-app"'), 'Workspace must render the React mount point');
}
$GLOBALS['pagenow'] = 'tools.php';
$_GET['page'] = 'rrze-qr';
try { $main->rrze_qr_redirect_legacy_page(); throw new RuntimeException('Legacy page was not redirected'); }
catch (JsonResponse $response) { check($response->data === 'https://example.test/wp-admin/admin.php?page=rrze-qr', 'Legacy links should open the new workspace'); }
echo "PHP workspace checks passed.\n";

$GLOBALS['assets'] = []; $GLOBALS['localized'] = [];
$main->rrze_qr_enqueue_scripts('index.php');
check(empty($GLOBALS['assets']) && empty($GLOBALS['localized']), 'Unrelated admin screens must not load QR assets or configuration');
$main->rrze_qr_enqueue_scripts('edit.php');
check(empty($GLOBALS['assets']) && empty($GLOBALS['localized']), 'Post and page lists must not load QR assets or configuration');
$GLOBALS['caps'] = ['edit_pages' => true];
$main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
$dependencies = $GLOBALS['assets']['rrze-qr-admin']['dependencies'];
foreach (['wp-components', 'wp-element', 'wp-i18n'] as $handle) { check(in_array($handle, $dependencies, true), 'WordPress supplies ' . $handle); }
check(!in_array('react-jsx-runtime', $dependencies, true), 'Use the JSX transform compatible with WordPress 6.4');
$asset = require __DIR__ . '/../assets/js/admin.min.asset.php';
check($GLOBALS['assets']['rrze-qr-admin']['version'] === $asset['version'], 'Workspace must use its build hash');
check($GLOBALS['assets']['rrze-qr-css']['version'] === hash_file('sha256', __DIR__ . '/../assets/css/rrze-qr.min.css'), 'CSS must use its content hash');
check($GLOBALS['assets']['rrze-qr-css']['dependencies'] === ['wp-components'], 'Load the WordPress components stylesheet');
check(!$GLOBALS['localized']['rrzeQrAdmin']['canSaveDefaults'], 'Editors must not see the save defaults action');
check(!isset($GLOBALS['assets']['rrze-qr-js']), 'Workspace must not load the old jQuery interface');
unset($GLOBALS['options']['rrze_qr_defaults']);
$GLOBALS['options']['rrze_qr_foreground'] = 'fau';
$GLOBALS['options']['rrze_qr_background'] = 'white';
$main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
check($GLOBALS['localized']['rrzeQrAdmin']['defaults'] === ['foreground' => '#04316a', 'background' => '#ffffff', 'size' => 300], 'Existing site color defaults must survive the upgrade');
echo "PHP asset and migration checks passed.\n";

// Row actions and contextual workspace must share permission checks.
$GLOBALS['caps'] = ['edit_pages' => true];
$GLOBALS['posts'][42]->post_title = 'Contact <script>alert(1)</script> &amp; Support';
$actions = $main->rrze_qr_add_create_link([], $GLOBALS['posts'][42]);
check(array_keys($actions) === ['create_qr'], 'Exactly one QR row action must be available');
check(str_contains($actions['create_qr'], 'post_id=42') && !str_contains($actions['create_qr'], 'href="#"'), 'Create action must link to the contextual workspace');
check(str_contains($actions['create_qr'], 'aria-label="Create QR code for Contact') && !str_contains($actions['create_qr'], '<script>'), 'Contextual labels must be escaped');
$_GET['post_id'] = '42';
foreach (['post', 'page'] as $type) {
    $GLOBALS['posts'][42]->post_type = $type;
    $main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
    $config = $GLOBALS['localized']['rrzeQrAdmin'];
    check(str_contains($config['context']['title'], '& Support') && !str_contains($config['context']['title'], '&amp;'), 'Workspace titles must contain readable text, not HTML entities');
    check($config['initialUrl'] === get_permalink(42), 'Workspace must prefill the current permalink');
    check($config['context']['filename'] === 'qr-code-contact-42.png', 'Workspace must reuse the descriptive filename');
    check($config['context']['backUrl'] === admin_url($type === 'page' ? 'edit.php?post_type=page' : 'edit.php'), 'Back link must match the source list');
}
// Unsupported content types must not gain access via a manually constructed link.
$GLOBALS['posts'][42]->post_type = 'attachment';
check($main->rrze_qr_add_create_link([], $GLOBALS['posts'][42]) === [], 'Unsupported types must have no row action');
try { $main->rrze_qr_admin_page(); throw new RuntimeException('Unsupported context succeeded'); }
catch (JsonResponse $response) { check($response->status === 403, 'Unsupported types must not load the workspace'); }
$GLOBALS['posts'][42]->post_type = 'page';
foreach (['', '0', '-1', '42x', '1.5', ['42'], str_repeat('9', 40)] as $invalid) {
    $_GET['post_id'] = $invalid;
    try { $main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr'); throw new RuntimeException('Invalid context succeeded'); }
    catch (JsonResponse $response) { check($response->status === 400, 'Malformed workspace IDs must be rejected'); }
}
foreach (['draft', 'private', 'trash', 'future'] as $status) {
    $_GET['post_id'] = '42';
    $GLOBALS['posts'][42]->post_status = $status;
    check($main->rrze_qr_add_create_link([], $GLOBALS['posts'][42]) === [], 'Non-public posts must have no row action');
    try { $main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr'); throw new RuntimeException('Non-public context succeeded'); }
    catch (JsonResponse $response) { check($response->status === 403, 'Non-public posts must not prefill the workspace'); }
}
$GLOBALS['posts'][42]->post_status = 'publish';
foreach (['99', '42'] as $id) {
    $_GET['post_id'] = $id;
    $GLOBALS['allowed'] = false;
    try { $main->rrze_qr_admin_page(); throw new RuntimeException('Unauthorized context succeeded'); }
    catch (JsonResponse $response) { check($response->status === 403, 'Missing or unauthorized posts must not expose context'); }
}
$GLOBALS['allowed'] = true;
unset($_GET['post_id']);
$main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
check($GLOBALS['localized']['rrzeQrAdmin']['context'] === null, 'Standalone generator must remain available');
echo "PHP row action and contextual workspace checks passed.\n";

// Capabilities mirror the WordPress author/editor roles, with per-item access
// supplied independently to ensure list links and direct workspace access agree.
foreach ([
    'author' => ['read' => true, 'edit_posts' => true, 'edit_published_posts' => true],
    'editor' => ['read' => true, 'edit_posts' => true, 'edit_pages' => true, 'edit_others_posts' => true, 'edit_others_pages' => true],
] as $role => $caps) {
    $GLOBALS['caps'] = $caps;
    $main->rrze_qr_admin_menu();
    check(!empty($caps[$GLOBALS['menu'][2]]), "$role must have access to the QR menu");
    $GLOBALS['posts'][42]->post_type = 'post';
    $GLOBALS['allowed'] = true;
    check(count($main->rrze_qr_add_create_link([], $GLOBALS['posts'][42])) === 1, "$role must be able to create QR codes for editable posts");
    $_GET['post_id'] = '42';
    $main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
    check($GLOBALS['localized']['rrzeQrAdmin']['initialUrl'] === get_permalink(42), "$role must be able to open the contextual workspace");
    check(!$GLOBALS['localized']['rrzeQrAdmin']['canSaveDefaults'], "$role must not see Save as defaults");
    ob_start(); $main->rrze_qr_admin_page(); $html = ob_get_clean();
    check(str_contains($html, 'id="rrze-qr-app"'), "$role must be able to render the workspace");
    check(save_defaults($main, ['foreground' => 'black', 'background' => 'white', 'size' => '300'])->status === 403, "$role must not save site defaults");
    $GLOBALS['allowed'] = false;
    check($main->rrze_qr_add_create_link([], $GLOBALS['posts'][42]) === [], 'Non-editable posts must not have a QR action');
    try { $main->rrze_qr_admin_page(); throw new RuntimeException('Non-editable post context succeeded'); }
    catch (JsonResponse $response) { check($response->status === 403, 'Direct links must also enforce per-post editing permission'); }
    $GLOBALS['allowed'] = true;
    unset($_GET['post_id']);
    $main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
    check($GLOBALS['localized']['rrzeQrAdmin']['context'] === null, "$role must also access the standalone generator");
}
$GLOBALS['caps'] = ['read' => true];
check($main->rrze_qr_add_create_link([], $GLOBALS['posts'][42]) === [], 'Subscribers must not have a QR action');
try { $main->rrze_qr_admin_page(); throw new RuntimeException('Subscriber access succeeded'); }
catch (JsonResponse $response) { check($response->status === 403, 'Subscribers must not access the workspace'); }
echo "PHP author/editor access checks passed.\n";


$GLOBALS['caps'] = ['manage_options' => true];
foreach (['128', '512', '0750', '4096'] as $size) {
    $response = save_defaults($main, ['foreground' => 'black', 'background' => 'white', 'size' => $size]);
    check($response->success && $response->data['size'] === (int) $size, 'Custom sizes must be stored as integers');
    $main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
    check($GLOBALS['localized']['rrzeQrAdmin']['defaults']['size'] === (int) $size, 'Custom sizes must survive reloading the workspace');
}
$before = $GLOBALS['options']['rrze_qr_defaults'];
foreach ([null, '', ' ', '0', '-1', '127', '4097', '512.5', '1e3', '512px', ' 512', ['512'], true, 512.5] as $size) {
    check(save_defaults($main, ['foreground' => 'black', 'background' => 'white', 'size' => $size])->status === 400, 'Malformed or out-of-range sizes must be rejected');
    check($GLOBALS['options']['rrze_qr_defaults'] === $before, 'Invalid sizes must not replace saved defaults');
}
$GLOBALS['options']['rrze_qr_defaults']['size'] = 99999;
$main->rrze_qr_enqueue_scripts('toplevel_page_rrze-qr');
check($GLOBALS['localized']['rrzeQrAdmin']['defaults']['size'] === 300, 'Invalid stored sizes must fall back to the default');
echo "PHP custom export size checks passed.\n";
