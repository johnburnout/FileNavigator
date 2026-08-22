<?php
namespace WPFB\Shortcode;

use WPFB\FileBrowser\FileBrowserService;

class FileBrowserShortcode
{
    private static ?\Twig\Environment $twig = null;

    private static function getTwig(): \Twig\Environment
    {
        if (self::$twig === null) {
            $loader = new \Twig\Loader\FilesystemLoader(WPFB_PLUGIN_DIR . 'templates');
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
        ], $atts, 'filebrowser');

        $rootPath = self::getRootPath($atts['root']);
        if (!$rootPath || !is_dir($rootPath)) {
            return '<p style="color:red;">Erreur : dossier racine invalide.</p>';
        }

        $currentDir = isset($_GET['fb_dir']) ? sanitize_text_field($_GET['fb_dir']) : '';
        $currentDir = str_replace('..', '', $currentDir);
        error_log('fb_dir reçu : ' . $currentDir);

        $extensions = array_map('trim', explode(',', $atts['extensions']));
        $extensions = array_filter($extensions);

        $options = [
            'extensions' => $extensions,
            'sort' => $atts['sort'],
            'order' => $atts['order'],
            'show_hidden' => filter_var($atts['show_hidden'], FILTER_VALIDATE_BOOLEAN),
        ];

        try {
            $service = new FileBrowserService($rootPath);
            $items = $service->listDirectory($currentDir, $options);
            error_log('Nombre d\'éléments : ' . count($items));
            foreach ($items as $item) {
                error_log(' - ' . $item['name'] . ' (isDir=' . ($item['isDir'] ? 'true' : 'false') . ', relative=' . $item['relative'] . ')');
            }
            $breadcrumb = self::buildBreadcrumb($rootPath, $currentDir);

            // Passer le rootPath au template pour l'utiliser dans les appels AJAX
            $twig = self::getTwig();
            return $twig->render('filebrowser.twig', [
                'items' => $items,
                'breadcrumb' => $breadcrumb,
                'current_dir' => $currentDir,
                'root_path' => $rootPath, // important pour JS
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpfb_ajax_nonce'),
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

        // Si c'est un chemin absolu (commence par /)
        if (strpos($rootParam, '/') === 0) {
            return realpath($rootParam) ?: $rootParam;
        }

        // Sinon, on le considère relatif au dossier uploads
        $path = $base . '/' . ltrim($rootParam, '/');
        $real = realpath($path);
        if ($real !== false && (is_dir($real) || is_file($real))) {
            return $real;
        }

        // Fallback : essayer à la racine de WordPress
        $fallback = ABSPATH . ltrim($rootParam, '/');
        if (is_dir($fallback) || is_file($fallback)) {
            return $fallback;
        }

        // Retourner le chemin même s'il n'existe pas (le service lèvera une exception)
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