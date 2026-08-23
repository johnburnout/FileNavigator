<?php
namespace WPFN\Shortcode;

use WPFN\FileNavigator\FileNavigatorService;

class FileNavigatorShortcode
{
    private static ?\Twig\Environment $twig = null;

    private static function getTwig(): \Twig\Environment
    {
        if (self::$twig === null) {
            $loader = new \Twig\Loader\FilesystemLoader(WPFN_PLUGIN_DIR . 'templates');
            self::$twig = new \Twig\Environment($loader, [
                'cache' => false,
                'autoescape' => 'html',
            ]);

            // Fonctions
            self::$twig->addFunction(new \Twig\TwigFunction('size_format', 'size_format'));
            self::$twig->addFunction(new \Twig\TwigFunction('date_i18n', 'date_i18n'));
            self::$twig->addFunction(new \Twig\TwigFunction('add_query_arg', 'add_query_arg'));
            self::$twig->addFunction(new \Twig\TwigFunction('get_option', 'get_option'));

            // Filtres
            self::$twig->addFilter(new \Twig\TwigFilter('esc_url', 'esc_url'));
            self::$twig->addFilter(new \Twig\TwigFilter('esc_html', 'esc_html'));
            self::$twig->addFilter(new \Twig\TwigFilter('esc_attr', 'esc_attr'));
            self::$twig->addFilter(new \Twig\TwigFilter('size_format', 'size_format'));
            self::$twig->addFilter(new \Twig\TwigFilter('date_i18n', 'date_i18n'));

            // Filtre pour l'URL des fichiers
            self::$twig->addFilter(new \Twig\TwigFilter('WPFN_file_url', function($relative, $rootPath) {
                if (empty($relative)) {
                    return '';
                }
                $absolute = $rootPath . '/' . ltrim($relative, '/');
                if (strpos($absolute, ABSPATH) === 0) {
                    return home_url(str_replace(ABSPATH, '', $absolute));
                }
                return $absolute;
            }));
        }
        return self::$twig;
    }

    public static function render($atts): string
    {
        $atts = shortcode_atts([
            'root' => '',
            'extensions' => '',
            'sort' => 'name',
            'order' => 'asc',
            'show_hidden' => 'false',
            'public_download' => 'true',
            'width' => '100%',
            'height' => '400px',
            'frame' => 'false',           // NOUVEAU
            'background' => '',           // NOUVEAU
            'toolbar_background' => '',    // NOUVEAU
            'breadcrumb_background' => '', // NOUVEAU
        ], $atts, 'filenavigator');

        // Récupération des paramètres GET pour le tri
        $atts['sort'] = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : $atts['sort'];
        $atts['order'] = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : $atts['order'];

        $rootPath = self::getRootPath($atts['root']);
        if (!$rootPath || !is_dir($rootPath)) {
            return '<p style="color:red;">Erreur : dossier racine invalide.</p>';
        }

        $currentDir = isset($_GET['fb_dir']) ? sanitize_text_field($_GET['fb_dir']) : '';
        $currentDir = str_replace('..', '', $currentDir);

        $extensions = array_map('trim', explode(',', $atts['extensions']));
        $extensions = array_filter($extensions);

        $options = [
            'extensions' => $extensions,
            'sort' => $atts['sort'],
            'order' => $atts['order'],
            'show_hidden' => filter_var($atts['show_hidden'], FILTER_VALIDATE_BOOLEAN),
        ];

        try {
            $service = new FileNavigatorService($rootPath);
            $items = $service->listDirectory($currentDir, $options);
            $breadcrumb = self::buildBreadcrumb($rootPath, $currentDir);

            // Permissions
            $public_download = filter_var($atts['public_download'], FILTER_VALIDATE_BOOLEAN);
            $permissions = [
                'can_upload'   => current_user_can('upload_files'),
                'can_download' => $public_download || current_user_can('read'),
                'can_copy'     => current_user_can('edit_posts'),
                'can_cut'      => current_user_can('edit_posts'),
                'can_paste'    => current_user_can('edit_posts'),
                'can_delete'   => current_user_can('delete_posts'),
                'can_rename'   => current_user_can('edit_posts'),
                'can_mkdir'    => current_user_can('edit_posts'),
            ];
            $permissions = apply_filters('WPFN_permissions', $permissions, $atts);

            $twig = self::getTwig();
            return $twig->render('filenavigator.twig', [
                'items' => $items,
                'breadcrumb' => $breadcrumb,
                'current_dir' => $currentDir,
                'root_path' => $rootPath,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('WPFN_ajax_nonce'),
                'permissions' => $permissions,
                'container_width' => $atts['width'],
                'container_height' => $atts['height'],
                'current_sort' => $atts['sort'],
                'current_order' => $atts['order'],
                'frame' => filter_var($atts['frame'], FILTER_VALIDATE_BOOLEAN),
                'background' => sanitize_text_field($atts['background']),                
                'toolbar_background' => sanitize_text_field($atts['toolbar_background']),
                'breadcrumb_background' => sanitize_text_field($atts['breadcrumb_background']),
            ]);

        } catch (\Exception $e) {
            return '<p style="color:red;">Erreur : ' . esc_html($e->getMessage()) . '</p>';
        }
    }

    private static function getRootPath(string $rootParam): string
    {
        $upload_dir = wp_upload_dir();
        $base = realpath($upload_dir['basedir']) ?: $upload_dir['basedir'];

        if (empty($rootParam)) {
            return $base;
        }

        if (strpos($rootParam, '/') === 0) {
            return realpath($rootParam) ?: $rootParam;
        }

        $path = $base . '/' . ltrim($rootParam, '/');
        $real = realpath($path);
        if ($real !== false && (is_dir($real) || is_file($real))) {
            return $real;
        }

        $fallback = ABSPATH . ltrim($rootParam, '/');
        if (is_dir($fallback) || is_file($fallback)) {
            return $fallback;
        }

        return $path;
    }

    private static function buildBreadcrumb(string $root, string $currentRelative): array
    {
        $parts = [['label' => 'Racine', 'path' => '']];
        if (empty($currentRelative)) {
            return $parts;
        }
        $segments = explode('/', trim($currentRelative, '/'));
        $accum = '';
        foreach ($segments as $seg) {
            $accum .= ($accum ? '/' : '') . $seg;
            $parts[] = ['label' => $seg, 'path' => $accum];
        }
        return $parts;
    }
}