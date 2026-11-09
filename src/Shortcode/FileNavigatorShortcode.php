<?php
namespace WPFN\Shortcode;

use WPFN\FileNavigator\FileNavigatorService;
use WPFN\FileNavigator\RootSignature;

class FileNavigatorShortcode
{
    private static ?\Twig\Environment $twig = null;

    /** Numéro du navigateur dans la page (plusieurs shortcodes possibles). */
    private static int $instance = 0;

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

            // URL publique d'un fichier ('' s'il n'est pas servi directement par le serveur web :
            // le template utilise alors le lien de téléchargement)
            self::$twig->addFilter(new \Twig\TwigFilter('WPFN_file_url', [self::class, 'publicUrl']));
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
            'public_download' => 'false',
            'width' => '100%',
            'height' => '400px',
            'frame' => 'false',           // NOUVEAU
            'background' => '',           // NOUVEAU
            'toolbar_background' => '',    // NOUVEAU
            'breadcrumb_background' => '', // NOUVEAU
        ], $atts, 'filenavigator');

        // Paramètres d'URL propres à chaque navigateur de la page : « fb_dir », « sort », « order »
        // pour le premier (liens existants inchangés), « fb_dir_2 », « sort_2 »… pour les suivants
        $instance = ++self::$instance;
        $suffix = $instance === 1 ? '' : '_' . $instance;

        // Tri : paramètres GET prioritaires, valeurs limitées à une liste blanche
        $atts['sort'] = self::choice(self::query('sort' . $suffix) ?? $atts['sort'], ['name', 'size', 'mtime'], 'name');
        $atts['order'] = self::choice(self::query('order' . $suffix) ?? $atts['order'], ['asc', 'desc'], 'asc');

        $rootPath = self::getRootPath((string) $atts['root']);
        if ($rootPath === '') {
            return '<p style="color:red;">Erreur : dossier racine invalide (il doit exister et se trouver dans wp-content/uploads).</p>';
        }
        $rootRel = RootSignature::relative($rootPath);

        $currentDir = self::query('fb_dir' . $suffix) ?? '';
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
                'root_rel' => $rootRel,
                'instance' => $instance,
                'param_suffix' => $suffix,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(\WPFN\Ajax\AjaxController::NONCE_ACTION),
                'permissions' => $permissions,
                'root_sig' => RootSignature::sign($rootRel, $public_download),
                'root_pd' => $public_download ? '1' : '0',
                'allowed_ext' => implode(',', (new \WPFN\FileNavigator\UploadValidator())->allowedExtensions()),
                'blocked_ext' => implode(',', \WPFN\FileNavigator\UploadValidator::BLOCKED_EXTENSIONS),
                'max_upload' => (int) wp_max_upload_size(),
                'max_files' => (int) ini_get('max_file_uploads') ?: 20,
                'container_width' => self::cssLength($atts['width'], '100%'),
                'container_height' => self::cssLength($atts['height'], '400px'),
                'current_sort' => $atts['sort'],
                'current_order' => $atts['order'],
                'frame' => filter_var($atts['frame'], FILTER_VALIDATE_BOOLEAN),
                'background' => self::cssColor($atts['background']),
                'toolbar_background' => self::cssColor($atts['toolbar_background']),
                'breadcrumb_background' => self::cssColor($atts['breadcrumb_background']),
            ]);

        } catch (\Exception $e) {
            return '<p style="color:red;">Erreur : ' . esc_html($e->getMessage()) . '</p>';
        }
    }

    /**
     * Chemin réel du dossier racine, ou '' s'il n'existe pas ou sort de la base autorisée
     * (wp-content/uploads par défaut, filtre « WPFN_root_base »).
     * « root » est relatif à cette base ; un chemin absolu n'est accepté que s'il s'y trouve.
     */
    private static function getRootPath(string $rootParam): string
    {
        if (!str_starts_with($rootParam, '/')) {
            return RootSignature::resolve($rootParam) ?? '';
        }
        $real = realpath($rootParam);
        return $real !== false && is_dir($real) && RootSignature::isAllowed($real) ? $real : '';
    }

    /** URL publique d'un fichier situé dans wp-content/uploads, sinon ''. */
    public static function publicUrl(string $relative, string $rootPath): string
    {
        if ($relative === '') {
            return '';
        }
        $uploads = wp_upload_dir();
        $base = rtrim(realpath($uploads['basedir']) ?: $uploads['basedir'], '/');
        $absolute = rtrim($rootPath, '/') . '/' . ltrim($relative, '/');
        if (!str_starts_with($absolute, $base . '/')) {
            return '';
        }
        $segments = explode('/', substr($absolute, strlen($base) + 1));
        return rtrim($uploads['baseurl'], '/') . '/' . implode('/', array_map('rawurlencode', $segments));
    }

    /** Paramètre GET texte, déséchappé et nettoyé (null si absent ou non textuel). */
    private static function query(string $key): ?string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : null;
    }

    private static function choice(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /** Couleur CSS sûre (#hex, rgb()/rgba()/hsl()/hsla(), nom) ; '' si invalide. */
    private static function cssColor(string $value): string
    {
        $value = trim($value);
        $ok = preg_match('/^#[0-9a-f]{3,8}$/i', $value)
            || preg_match('/^(rgb|hsl)a?\(\s*[0-9.,%\s\/]+\)$/i', $value)
            || preg_match('/^[a-z]{3,30}$/i', $value);
        return $ok ? $value : '';
    }

    /** Longueur CSS sûre (nombre + unité, ou auto) ; $default si invalide. */
    private static function cssLength(string $value, string $default): string
    {
        $value = trim($value);
        return preg_match('/^(auto|\d+(\.\d+)?(px|%|em|rem|vh|vw|vmin|vmax|ch)?)$/i', $value) ? $value : $default;
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