<?php
    /**
    * Plugin Name: WP FileBrowser
    * Description: Navigateur de fichiers avec shortcode basé sur Symfony Finder et Twig.
    * Version: 1.0.0
    * Author: Votre nom
    */
    
    if (!defined('ABSPATH')) {
        exit;
    }
    
    require_once __DIR__ . '/vendor/autoload.php';
    
    define('WPFB_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('WPFB_PLUGIN_URL', plugin_dir_url(__FILE__));
    
    add_action('init', function() {
        add_shortcode('filebrowser', ['WPFB\Shortcode\FileBrowserShortcode', 'render']);
    });
    
    add_action('wp_enqueue_scripts', function() {
        wp_enqueue_style('wpfb-style', WPFB_PLUGIN_URL . 'assets/css/style.css');
    });
    
// Inclure les classes (ou utiliser l'autoloader PSR-4)
require_once WPFB_PLUGIN_DIR . 'src/Shortcode/FileBrowserShortcode.php';
require_once WPFB_PLUGIN_DIR . 'src/FileBrowser/FileBrowserService.php';
require_once WPFB_PLUGIN_DIR . 'src/FileBrowser/SecurityChecker.php';