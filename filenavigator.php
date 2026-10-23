<?php
/**
* Plugin Name: File Navigator
* Description: Navigateur de fichiers avec shortcode, Symfony Finder, Twig et barre d'outils (upload, download, copier, couper, coller, supprimer, renommer, créer dossier, tri dynamique).
* Version: 1.3.0
* Author: Jean Roussie
* Author URI: https://github.com/johnburnout
* Icon: assets/img/icon.svg
* Text Domain: filenavigator
* Domain Path: /languages
* Requires at least: 5.8
* Requires PHP: 8.1
* Update URI: https://github.com/johnburnout/FileNavigator
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

define('WPFN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Mises à jour automatiques depuis les releases GitHub
if (is_admin() || wp_doing_cron()) {
    (new \WPFN\Updater\GitHubUpdater(__FILE__, 'johnburnout', 'FileNavigator'))->register();
}

add_action('init', function() {
    add_shortcode('filenavigator', ['WPFN\Shortcode\FileNavigatorShortcode', 'render']);
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('WPFN-style', WPFN_PLUGIN_URL . 'assets/css/style.css', [], '1.3.0');
    wp_enqueue_script('WPFN-script', WPFN_PLUGIN_URL . 'assets/js/main.js', ['jquery'], '1.3.0', true);
});

// ================================
// AJAX endpoints
// ================================

add_action('wp_ajax_WPFN_upload', 'WPFN_ajax_upload');
add_action('wp_ajax_WPFN_check_exists', 'WPFN_ajax_check_exists');
add_action('wp_ajax_WPFN_prepare_upload', 'WPFN_ajax_prepare_upload');
add_action('wp_ajax_WPFN_download', 'WPFN_ajax_download'); // on garde wp_ajax_nopriv aussi pour non connectés
add_action('wp_ajax_nopriv_WPFN_download', 'WPFN_ajax_download'); // autoriser les non connectés
add_action('wp_ajax_WPFN_delete', 'WPFN_ajax_delete');
add_action('wp_ajax_WPFN_rename', 'WPFN_ajax_rename');
add_action('wp_ajax_WPFN_paste', 'WPFN_ajax_paste');
add_action('wp_ajax_WPFN_mkdir', 'WPFN_ajax_mkdir');

// ---------- UPLOAD ----------
function WPFN_ajax_upload() {
    check_ajax_referer('WPFN_ajax_nonce', 'nonce');
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $root = WPFN_request_root();
    $currentDir = WPFN_request_dir();
    $paths = WPFN_request_json('paths');   // chemins relatifs (glisser-déposer de dossiers)
    $dirs = WPFN_request_json('dirs') ?? []; // dossiers vides à créer
    $files = $_FILES['files'] ?? ['tmp_name' => []];

    try {
        $service = new \WPFN\FileNavigator\FileOperationService($root);
        $result = $service->upload($files, $currentDir, $paths, $dirs);
        wp_send_json_success($result); // ['files' => [...], 'rejected' => [['path', 'reason'], ...]]
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- Helpers de requête ----------
/**
 * Signature du dossier racine : le navigateur renvoie le chemin reçu du shortcode,
 * le serveur vérifie qu'il n'a pas été modifié (sinon n'importe quel dossier du serveur
 * serait accessible en changeant le paramètre « root »).
 */
function WPFN_sign_root(string $root, bool $publicDownload = false): string {
    return hash_hmac('sha256', 'WPFN_root|' . ($publicDownload ? '1' : '0') . '|' . $root, wp_salt('nonce'));
}

/** Option « public_download » du shortcode, vérifiée par la signature (cf. WPFN_request_root). */
function WPFN_request_public_download(): bool {
    return !empty($GLOBALS['WPFN_public_download']);
}

/**
 * Dossier racine de la requête, après vérification de sa signature.
 * Sans « root », on utilise le dossier uploads de WordPress.
 */
function WPFN_request_root(bool $json = true): string {
    $src = $json ? $_POST : $_GET;
    $root = isset($src['root']) ? (string) wp_unslash($src['root']) : '';
    $sig = isset($src['root_sig']) ? (string) wp_unslash($src['root_sig']) : '';
    $publicDownload = isset($src['root_pd']) && (string) $src['root_pd'] === '1';
    $GLOBALS['WPFN_public_download'] = false;

    if ($root === '') {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'];
    }
    if ($sig === '' || !hash_equals(WPFN_sign_root($root, $publicDownload), $sig)) {
        $message = 'Dossier racine non autorisé.';
        if ($json) {
            wp_send_json_error(['message' => $message], 403);
        }
        wp_die(esc_html($message), '', ['response' => 403]);
    }
    $GLOBALS['WPFN_public_download'] = $publicDownload;
    return $root;
}

function WPFN_request_dir(): string {
    return isset($_POST['current_dir']) ? sanitize_text_field(wp_unslash($_POST['current_dir'])) : '';
}

function WPFN_request_json(string $key): ?array {
    if (!isset($_POST[$key])) {
        return null;
    }
    $value = json_decode(wp_unslash($_POST[$key]), true);
    return is_array($value) ? $value : null;
}

// ---------- UPLOAD : détection des conflits ----------
function WPFN_ajax_check_exists() {
    check_ajax_referer('WPFN_ajax_nonce', 'nonce');
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }
    $names = WPFN_request_json('names') ?? [];

    try {
        $service = new \WPFN\FileNavigator\FileOperationService(WPFN_request_root());
        wp_send_json_success(['existing' => $service->findExisting($names, WPFN_request_dir())]);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- UPLOAD : application des choix (remplacer / fusionner / garder les deux) ----------
function WPFN_ajax_prepare_upload() {
    check_ajax_referer('WPFN_ajax_nonce', 'nonce');
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }
    $items = WPFN_request_json('items') ?? [];

    // Remplacer ou fusionner modifie/supprime l'existant : droit de suppression requis
    foreach ($items as $item) {
        $action = $item['action'] ?? 'new';
        if (in_array($action, ['replace', 'merge'], true) && !current_user_can('delete_posts')) {
            wp_send_json_error(['message' => 'Permission refusée : vous ne pouvez pas remplacer ou fusionner des éléments existants.']);
        }
    }

    try {
        $service = new \WPFN\FileNavigator\FileOperationService(WPFN_request_root());
        $mapping = $service->prepareUpload($items, WPFN_request_dir());
        wp_send_json_success(['mapping' => (object) $mapping]);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- DOWNLOAD (public) ----------
function WPFN_ajax_download() {
    // Pas de vérification de capacité pour autoriser tout le monde
    $paths = isset($_GET['paths']) ? json_decode(stripslashes($_GET['paths']), true) : [];
    if (empty($paths)) {
        wp_die('Aucun fichier sélectionné.');
    }

    $root = WPFN_request_root(false);
    // public_download="false" : réservé aux utilisateurs connectés
    if (!WPFN_request_public_download() && !current_user_can('read')) {
        wp_die(esc_html('Connectez-vous pour télécharger ce fichier.'), '', ['response' => 403]);
    }

    $service = new \WPFN\FileNavigator\FileOperationService($root);
    try {
        $service->download($paths);
    } catch (\Exception $e) {
        wp_die($e->getMessage());
    }
}

// ---------- DELETE ----------
function WPFN_ajax_delete() {
    check_ajax_referer('WPFN_ajax_nonce', 'nonce');
    if (!current_user_can('delete_posts')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $paths = isset($_POST['paths']) ? json_decode(stripslashes($_POST['paths']), true) : [];
    if (empty($paths)) {
        wp_send_json_error(['message' => 'Aucun élément sélectionné.']);
    }

    $root = WPFN_request_root();

    $service = new \WPFN\FileNavigator\FileOperationService($root);
    try {
        $service->delete($paths);
        wp_send_json_success();
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- RENAME ----------
function WPFN_ajax_rename() {
    check_ajax_referer('WPFN_ajax_nonce', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $old = isset($_POST['old_path']) ? sanitize_text_field($_POST['old_path']) : '';
    $newName = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : '';
    if (empty($old) || empty($newName)) {
        wp_send_json_error(['message' => 'Paramètres manquants.']);
    }

    $root = WPFN_request_root();

    $service = new \WPFN\FileNavigator\FileOperationService($root);
    try {
        $service->rename($old, $newName);
        wp_send_json_success();
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- PASTE ----------
function WPFN_ajax_paste() {
    check_ajax_referer('WPFN_ajax_nonce', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $paths = isset($_POST['paths']) ? json_decode(stripslashes($_POST['paths']), true) : [];
    $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'copy';
    $sourceDir = isset($_POST['source_dir']) ? sanitize_text_field($_POST['source_dir']) : '';
    $targetDir = isset($_POST['target_dir']) ? sanitize_text_field($_POST['target_dir']) : '';
    if (empty($paths) || empty($targetDir)) {
        wp_send_json_error(['message' => 'Paramètres manquants.']);
    }

    $root = WPFN_request_root();

    $service = new \WPFN\FileNavigator\FileOperationService($root);
    try {
        $service->paste($paths, $sourceDir, $targetDir, $mode);
        wp_send_json_success();
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- MKDIR ----------
function WPFN_ajax_mkdir() {
    check_ajax_referer('WPFN_ajax_nonce', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $newDir = isset($_POST['new_dir']) ? sanitize_text_field($_POST['new_dir']) : '';
    $currentDir = isset($_POST['current_dir']) ? sanitize_text_field($_POST['current_dir']) : '';
    if (empty($newDir)) {
        wp_send_json_error(['message' => 'Nom du dossier manquant.']);
    }

    $root = WPFN_request_root();

    try {
        $service = new \WPFN\FileNavigator\FileOperationService($root);
        $service->createDirectory($newDir, $currentDir);
        wp_send_json_success(['message' => 'Dossier créé avec succès.']);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}