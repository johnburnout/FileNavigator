<?php
/**
 * Plugin Name: WP FileBrowser
 * Description: Navigateur de fichiers avec shortcode, Symfony Finder, Twig et barre d'outils (upload, download, copier, couper, coller, supprimer, renommer).
 * Version: 1.1.0
 * Author: Votre nom
 * Icon: assets/img/icon.svg
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

define('WPFB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Enregistrer la shortcode
add_action('init', function() {
    add_shortcode('filebrowser', ['WPFB\Shortcode\FileBrowserShortcode', 'render']);
});

// Charger les assets (CSS/JS)
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('wpfb-style', WPFB_PLUGIN_URL . 'assets/css/style.css');
    wp_enqueue_script('wpfb-script', WPFB_PLUGIN_URL . 'assets/js/main.js', ['jquery'], '1.1', true);
});

// ================================
// Gestion des requêtes AJAX
// ================================

add_action('wp_ajax_wpfb_upload', 'wpfb_ajax_upload');
add_action('wp_ajax_wpfb_download', 'wpfb_ajax_download');
add_action('wp_ajax_wpfb_delete', 'wpfb_ajax_delete');
add_action('wp_ajax_wpfb_rename', 'wpfb_ajax_rename');
add_action('wp_ajax_wpfb_paste', 'wpfb_ajax_paste');
add_action('wp_ajax_wpfb_mkdir', 'wpfb_ajax_mkdir');

// ---------- UPLOAD ----------
function wpfb_ajax_upload() {
    check_ajax_referer('wpfb_ajax_nonce', 'nonce');
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }
    $currentDir = isset($_POST['current_dir']) ? sanitize_text_field($_POST['current_dir']) : '';

    try {
        $service = new \WPFB\FileBrowser\FileOperationService($root);
        $uploaded = $service->upload($_FILES['files'], $currentDir);
        wp_send_json_success(['files' => $uploaded]);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- DOWNLOAD ----------
function wpfb_ajax_download() {
    check_ajax_referer('wpfb_ajax_nonce', 'nonce');
    if (!current_user_can('read')) {
        wp_die('Permission refusée.');
    }

    $paths = isset($_GET['paths']) ? json_decode(stripslashes($_GET['paths']), true) : [];
    if (empty($paths)) {
        wp_die('Aucun fichier sélectionné.');
    }

    $root = isset($_GET['root']) ? sanitize_text_field($_GET['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

    $service = new \WPFB\FileBrowser\FileOperationService($root);
    try {
        $service->download($paths);
    } catch (\Exception $e) {
        wp_die($e->getMessage());
    }
}

// ---------- DELETE ----------
function wpfb_ajax_delete() {
    check_ajax_referer('wpfb_ajax_nonce', 'nonce');
    if (!current_user_can('delete_posts')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $paths = isset($_POST['paths']) ? json_decode(stripslashes($_POST['paths']), true) : [];
    if (empty($paths)) {
        wp_send_json_error(['message' => 'Aucun élément sélectionné.']);
    }

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

    $service = new \WPFB\FileBrowser\FileOperationService($root);
    try {
        $service->delete($paths);
        wp_send_json_success();
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- RENAME ----------
function wpfb_ajax_rename() {
    check_ajax_referer('wpfb_ajax_nonce', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $old = isset($_POST['old_path']) ? sanitize_text_field($_POST['old_path']) : '';
    $newName = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : '';
    if (empty($old) || empty($newName)) {
        wp_send_json_error(['message' => 'Paramètres manquants.']);
    }

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

    $service = new \WPFB\FileBrowser\FileOperationService($root);
    try {
        $service->rename($old, $newName);
        wp_send_json_success();
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- PASTE ----------
function wpfb_ajax_paste() {
    check_ajax_referer('wpfb_ajax_nonce', 'nonce');
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

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

    $service = new \WPFB\FileBrowser\FileOperationService($root);
    try {
        $service->paste($paths, $sourceDir, $targetDir, $mode);
        wp_send_json_success();
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ---------- MK (make directory) ----------
function wpfb_ajax_mkdir() {
    check_ajax_referer('wpfb_ajax_nonce', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission refusée.']);
    }

    $newDir = isset($_POST['new_dir']) ? sanitize_text_field($_POST['new_dir']) : '';
    $currentDir = isset($_POST['current_dir']) ? sanitize_text_field($_POST['current_dir']) : '';
    if (empty($newDir)) {
        wp_send_json_error(['message' => 'Nom du dossier manquant.']);
    }

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

    try {
        $service = new \WPFB\FileBrowser\FileOperationService($root);
        $service->createDirectory($newDir, $currentDir);
        wp_send_json_success(['message' => 'Dossier créé avec succès.']);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}