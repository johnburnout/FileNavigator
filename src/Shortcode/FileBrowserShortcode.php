<?php
namespace WPFB\Shortcode;

use WPFB\FileBrowser\FileBrowserService;

class FileBrowserShortcode
{
    private static ?\Twig\Environment $twig = null;

    /**
     * Initialise l'environnement Twig avec le dossier templates/
     * et enregistre les fonctions WordPress utiles.
     */
    private static function getTwig(): \Twig\Environment
    {
        if (self::$twig === null) {
            $loader = new \Twig\Loader\FilesystemLoader(WPFB_PLUGIN_DIR . 'templates');
        self::$twig = new \Twig\Environment($loader, [
                'cache' => false,
                'autoescape' => 'html',
            ]);
            
            // ---- Fonctions (appelables avec {{ function(...) }}) ----
        self::$twig->addFunction(new \Twig\TwigFunction('size_format', 'size_format'));
        self::$twig->addFunction(new \Twig\TwigFunction('date_i18n', 'date_i18n'));
        self::$twig->addFunction(new \Twig\TwigFunction('add_query_arg', 'add_query_arg'));
        self::$twig->addFunction(new \Twig\TwigFunction('get_option', 'get_option'));
            
            // ---- Filtres (utilisables avec {{ variable|filter }}) ----
        self::$twig->addFilter(new \Twig\TwigFilter('esc_url', 'esc_url'));
        self::$twig->addFilter(new \Twig\TwigFilter('esc_html', 'esc_html'));
        self::$twig->addFilter(new \Twig\TwigFilter('esc_attr', 'esc_attr'));
            
            // ⬇️ Ajout des deux filtres manquants
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
        error_log('=== DEBUG getRootPath ===');
        error_log('Root path: ' . $rootPath);
        error_log('is_dir: ' . (is_dir($rootPath) ? 'true' : 'false'));
        error_log('file_exists: ' . (file_exists($rootPath) ? 'true' : 'false'));
        
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
            $service = new FileBrowserService($rootPath);
            $items = $service->listDirectory($currentDir, $options);
            $breadcrumb = self::buildBreadcrumb($rootPath, $currentDir);

            // Rendu via Twig
            $twig = self::getTwig();
            return $twig->render('filebrowser.twig', [
                'items' => $items,
                'breadcrumb' => $breadcrumb,
                'current_dir' => $currentDir,
                // 'get' => $_GET,  // <-- supprimez cette ligne
            ]);

        } catch (\Exception $e) {
            return '<p style="color:red;">Erreur : ' . esc_html($e->getMessage()) . '</p>';
        }
    }

    private static function getRootPath(string $rootParam): string
    {
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'];
        
        if (empty($rootParam)) {
            return $base;
        }
        
        // Si c'est un chemin absolu (commence par /)
        if (strpos($rootParam, '/') === 0) {
            return $rootParam;
        }
        
        // Sinon, on le considère relatif au dossier uploads
        $path = $base . '/' . ltrim($rootParam, '/');
        
        // Si le chemin existe, on le retourne
        if (is_dir($path) || is_file($path)) {
            return $path;
        }
        
        // Fallback : essayer à la racine de WordPress (pour compatibilité)
        $fallback = ABSPATH . ltrim($rootParam, '/');
        if (is_dir($fallback) || is_file($fallback)) {
            return $fallback;
        }
        
        // Si rien n'existe, on retourne le chemin dans uploads (le service vérifiera)
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