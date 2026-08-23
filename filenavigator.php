<?php
/**
* Plugin Name: File Navigator
* Description: Navigateur de fichiers avec shortcode, Symfony Finder, Twig et barre d'outils (upload, download, copier, couper, coller, supprimer, renommer, créer dossier, tri dynamique).
* Version: 1.1.2
* Author: Jean Roussie
* Author URI: https://github.com/johnburnout
* Icon: assets/img/icon.svg
* Text Domain: filenavigator
* Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

define('WPFN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFN_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('init', function() {
    add_shortcode('filenavigator', ['WPFN\Shortcode\FileNavigatorShortcode', 'render']);
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('WPFN-style', WPFN_PLUGIN_URL . 'assets/css/style.css');
    wp_enqueue_script('WPFN-script', WPFN_PLUGIN_URL . 'assets/js/main.js', ['jquery'], '1.1', true);
});

// ================================
// AJAX endpoints
// ================================

add_action('wp_ajax_WPFN_upload', 'WPFN_ajax_upload');
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

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }
    $currentDir = isset($_POST['current_dir']) ? sanitize_text_field($_POST['current_dir']) : '';

    try {
        $service = new \WPFN\FileNavigator\FileOperationService($root);
        $uploaded = $service->upload($_FILES['files'], $currentDir);
        wp_send_json_success(['files' => $uploaded]);
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

    $root = isset($_GET['root']) ? sanitize_text_field($_GET['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
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

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

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

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

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

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

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

    $root = isset($_POST['root']) ? sanitize_text_field($_POST['root']) : '';
    if (empty($root)) {
        $upload_dir = wp_upload_dir();
        $root = $upload_dir['basedir'];
    }

    try {
        $service = new \WPFN\FileNavigator\FileOperationService($root);
        $service->createDirectory($newDir, $currentDir);
        wp_send_json_success(['message' => 'Dossier créé avec succès.']);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}