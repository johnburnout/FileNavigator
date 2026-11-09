<?php
/**
 * Amorçage des tests : autoload Composer et doublures minimales des fonctions WordPress
 * utilisées par les classes testées (aucune installation WordPress n'est nécessaire).
 *
 * Les tests règlent le comportement via $GLOBALS['wpfn_test'] :
 *  - 'upload_dir' : ['basedir' => …, 'baseurl' => …]
 *  - 'filters'    : [nom du filtre => valeur renvoyée]
 *  - 'logged_in'  : bool
 */
require dirname(__DIR__) . '/vendor/autoload.php';

define('ABSPATH', sys_get_temp_dir() . '/');
define('WPFN_PLUGIN_DIR', dirname(__DIR__) . '/');
define('WPFN_PLUGIN_URL', 'https://example.test/wp-content/plugins/filenavigator/');
define('MB_IN_BYTES', 1024 * 1024);
define('GB_IN_BYTES', 1024 * MB_IN_BYTES);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['wpfn_test'] = ['upload_dir' => ['basedir' => sys_get_temp_dir(), 'baseurl' => 'https://example.test/wp-content/uploads'], 'filters' => [], 'logged_in' => false];

function wp_upload_dir(): array { return $GLOBALS['wpfn_test']['upload_dir']; }
function apply_filters(string $name, $value, ...$args) { return $GLOBALS['wpfn_test']['filters'][$name] ?? $value; }
function wp_salt(string $scheme = 'auth'): string { return 'sel-de-test'; }
function sanitize_text_field($s): string { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function wp_unslash($v) { return is_string($v) ? stripslashes($v) : $v; }
function size_format($bytes): string { return $bytes . ' o'; }
function is_user_logged_in(): bool { return $GLOBALS['wpfn_test']['logged_in']; }
function esc_html($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function plugin_basename(string $file): string { return basename(dirname($file)) . '/' . basename($file); }
function get_file_data(string $file, array $headers): array { return array_map(fn() => '1.0.0', $headers); }

// Réseau et transients (GitHubUpdater) : réponses définies par URL dans $GLOBALS['wpfn_test']['http']
$GLOBALS['wpfn_test']['http'] = [];
$GLOBALS['wpfn_test']['transients'] = [];
$GLOBALS['wpfn_test']['requests'] = [];

class WP_Error
{
    public function __construct(public string $code = '', public string $message = '') {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}
function is_wp_error($v): bool { return $v instanceof WP_Error; }
function get_site_transient(string $k) { return $GLOBALS['wpfn_test']['transients'][$k] ?? false; }
function set_site_transient(string $k, $v, int $ttl = 0): bool { $GLOBALS['wpfn_test']['transients'][$k] = $v; return true; }
function delete_site_transient(string $k): bool { unset($GLOBALS['wpfn_test']['transients'][$k]); return true; }
function get_bloginfo(string $k = ''): string { return '6.9'; }
function home_url(string $p = ''): string { return 'https://example.test' . $p; }
function wp_remote_get(string $url, array $args = [])
{
    $GLOBALS['wpfn_test']['requests'][] = $url;
    $r = $GLOBALS['wpfn_test']['http'][$url] ?? ['code' => 404, 'body' => ''];
    return $r instanceof WP_Error ? $r : ['response' => ['code' => $r['code']], 'body' => $r['body']];
}
function wp_remote_retrieve_response_code($r) { return $r['response']['code'] ?? ''; }
function wp_remote_retrieve_body($r) { return $r['body'] ?? ''; }
function download_url(string $url)
{
    $GLOBALS['wpfn_test']['requests'][] = $url;
    $r = $GLOBALS['wpfn_test']['http'][$url] ?? null;
    if (!is_array($r) || $r['code'] !== 200) {
        return new WP_Error('http_404', 'Introuvable');
    }
    $file = tempnam(sys_get_temp_dir(), 'wpfn-dl-');
    file_put_contents($file, $r['body']);
    return $file;
}

// Rendu du shortcode (Twig + fonctions WordPress utilisées par le template)
function shortcode_atts(array $defaults, $atts, string $shortcode = ''): array { return array_merge($defaults, (array) $atts); }
function current_user_can(string $cap): bool { return $GLOBALS['wpfn_test']['logged_in']; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action = -1): string { return 'nonce-de-test'; }
function wp_max_upload_size(): int { return 8 * MB_IN_BYTES; }
function get_allowed_mime_types(): array { return ['txt' => 'text/plain', 'pdf' => 'application/pdf', 'png' => 'image/png']; }
function esc_url($url): string { return (string) $url; }
function esc_attr($s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
function add_query_arg($key, $value): string { return '?' . $key . '=' . urlencode((string) $value); }
function date_i18n(string $format, $timestamp): string { return date($format, (int) $timestamp); }
function get_option(string $name, $default = false) { return $default; }
