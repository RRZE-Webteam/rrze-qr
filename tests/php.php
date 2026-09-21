<?php
// Isolated endpoint regression checks; no WordPress database is modified.
define('ABSPATH', __DIR__ . '/');
class WP_Post
{
    public function __construct(public int $ID, public string $post_status = 'publish', public string $post_type = 'post') {}
}
class JsonResponse extends RuntimeException
{
    public function __construct(public bool $success, public mixed $data, public int $status) { parent::__construct(); }
}
function __($text, $domain) { return $text; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function add_settings_error($setting, $code, $message) { $GLOBALS['settings_errors'][] = $message; }
function wp_unslash($value) { return $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function settings_errors($group) {}
function settings_fields($group) {}
function submit_button() {}
function checked($actual, $expected) {}
function plugins_url($path, $file) { return 'https://example.test/plugins/rrze-qr/' . $path; }
function wp_enqueue_script($handle, $url, $dependencies, $version, $footer) { $GLOBALS['assets'][$handle] = compact('dependencies', 'version'); }
function wp_enqueue_style($handle, $url, $dependencies, $version) { $GLOBALS['assets'][$handle] = compact('dependencies', 'version'); }
function wp_localize_script($handle, $name, $data) { $GLOBALS['localized'] = $data; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'test'; }
function home_url($path) { return 'https://example.test' . $path; }
function check_ajax_referer($action, $field) {
    if (!$GLOBALS['valid_nonce']) { throw new JsonResponse(false, 'Invalid nonce', 403); }
}
function current_user_can($capability, $id = null) { return $GLOBALS['allowed'] && $id === 42; }
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
function request($main, $id): JsonResponse {
    $_POST = $id === null ? [] : ['post_id' => $id];
    try { $main->rrze_qr_get_permalink(); } catch (JsonResponse $response) { return $response; }
    throw new RuntimeException('Endpoint did not terminate');
}
foreach ([null, '', '0', '-1', '42x', '1.5', ['42'], str_repeat('9', 40)] as $invalid) {
    check(request($main, $invalid)->status === 400, 'Malformed IDs must be rejected');
}
check(request($main, '42')->success, 'Authorized published post should succeed');
check(request($main, '99')->status === 403, 'Missing post must be rejected');
foreach (['draft', 'private', 'trash', 'future'] as $status) {
    $GLOBALS['posts'][42]->post_status = $status;
    check(request($main, '42')->status === 403, 'Non-published posts must be rejected');
    check($main->rrze_qr_add_download_link([], $GLOBALS['posts'][42]) === [], 'Hidden posts must have no action');
}
$GLOBALS['posts'][42]->post_status = 'publish';
$GLOBALS['posts'][42]->post_type = 'attachment';
check(request($main, '42')->status === 403, 'Unsupported types must be rejected');
$GLOBALS['posts'][42]->post_type = 'page';
check(request($main, '42')->success, 'Authorized published page should succeed');
$GLOBALS['allowed'] = false;
check(request($main, '42')->status === 403, 'Nonce must not bypass capabilities');
check($main->rrze_qr_add_download_link([], $GLOBALS['posts'][42]) === [], 'Unauthorized users must have no action');
$GLOBALS['allowed'] = true;
$GLOBALS['valid_nonce'] = false;
check(request($main, '42')->status === 403, 'Invalid nonce must be rejected');
echo "PHP endpoint checks passed.\n";

foreach (['white', 'black', 'fau'] as $fg) {
    foreach (['white', 'black', 'fau', 'transparent'] as $bg) {
        $_POST = ['rrze_qr_foreground' => $fg, 'rrze_qr_background' => $bg];
        $valid = $bg === 'transparent' || ($fg !== $bg && ($fg === 'white' || $bg === 'white'));
        $actual = [$main->rrze_qr_save_foreground($fg), $main->rrze_qr_save_background($bg)];
        check($actual === ($valid ? [$fg, $bg] : ['black', 'white']), "Unexpected saved colors: $fg/$bg");
    }
}
check(!empty($GLOBALS['settings_errors']), 'Unsafe colors must show a settings error');
echo "PHP color checks passed.\n";
ob_start();
$main->rrze_qr_settings_page();
$settings_html = ob_get_clean();
check(str_contains($settings_html, 'id="rrze-qr-settings-preview"'), 'Settings must render the preview canvas');
check(str_contains($settings_html, 'id="rrze-qr-preview-surface"'), 'Transparent previews need a background selector');
echo "PHP preview checks passed.\n";
$main->rrze_qr_enqueue_scripts('index.php');
check(empty($GLOBALS['assets']) && empty($GLOBALS['localized']), 'Unrelated admin screens must not load QR assets or configuration');
$main->rrze_qr_enqueue_scripts('edit.php');
$asset = require __DIR__ . '/../assets/js/rrze-qr.min.asset.php';
check($GLOBALS['assets']['rrze-qr-js']['version'] === $asset['version'], 'JavaScript must use its build hash');
check($GLOBALS['assets']['rrze-qr-css']['version'] === hash_file('sha256', __DIR__ . '/../assets/css/rrze-qr.min.css'), 'CSS must use its content hash');
check(in_array('rrze-qr-qrious', $GLOBALS['assets']['rrze-qr-js']['dependencies'], true), 'Use a plugin-specific QRious handle');
echo "PHP asset checks passed.\n";
