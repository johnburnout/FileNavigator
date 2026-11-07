<?php
/**
* Plugin Name: File Navigator
* Description: Navigateur de fichiers avec shortcode, Symfony Finder, Twig et barre d'outils (upload, download, copier, couper, coller, supprimer, renommer, créer dossier, tri dynamique).
* Version: 1.3.1
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

define('WPFN_VERSION', '1.3.1');
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
    wp_enqueue_style('WPFN-style', WPFN_PLUGIN_URL . 'assets/css/style.css', [], WPFN_VERSION);
    wp_enqueue_script('WPFN-script', WPFN_PLUGIN_URL . 'assets/js/main.js', ['jquery'], WPFN_VERSION, true);
});

\WPFN\Ajax\AjaxController::register();
