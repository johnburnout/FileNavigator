<?php
namespace WPFN\Updater;

/**
 * Mises à jour automatiques du plugin à partir des releases GitHub.
 *
 * Fonctionnement :
 *  - interroge l'API GitHub (releases/latest), résultat mis en cache 6 h ;
 *  - si le tag (ex. "v1.2.0") est plus récent que la version installée,
 *    la mise à jour est injectée dans le transient "update_plugins" de WordPress ;
 *  - le paquet installé est l'asset ZIP attaché à la release (construit par
 *    GitHub Actions, il contient le dossier vendor/). Sans asset, aucune mise
 *    à jour n'est proposée : l'archive source de GitHub ne contient pas vendor/.
 *
 * Constantes optionnelles (wp-config.php) :
 *  - WPFN_GITHUB_TOKEN        : jeton GitHub (dépôt privé ou limite de requêtes) ;
 *  - WPFN_FORCE_AUTO_UPDATE   : true pour forcer l'installation automatique.
 */
class GitHubUpdater
{
    private const CACHE_KEY = 'wpfn_github_release';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;
    private const ERROR_TTL = 30 * MINUTE_IN_SECONDS;

    private string $pluginFile;
    private string $basename;   // ex. "filenavigator/filenavigator.php"
    private string $slug;       // ex. "filenavigator"
    private string $owner;
    private string $repo;
    private string $assetName;
    private string $version;

    public function __construct(string $pluginFile, string $owner, string $repo, string $assetName = 'filenavigator.zip')
    {
        $this->pluginFile = $pluginFile;
        $this->basename   = plugin_basename($pluginFile);
        $this->slug       = dirname($this->basename);
        $this->owner      = $owner;
        $this->repo       = $repo;
        $this->assetName  = $assetName;

        $data = get_file_data($pluginFile, ['Version' => 'Version']);
        $this->version = $data['Version'] ?: '0.0.0';
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'injectUpdate']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);
        add_filter('upgrader_source_selection', [$this, 'fixSourceFolder'], 10, 4);
        add_filter('http_request_args', [$this, 'authorizeDownload'], 10, 2);
        add_filter('auto_update_plugin', [$this, 'forceAutoUpdate'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'purgeCache'], 10, 2);

        // Lien « Vérifier les mises à jour » dans la liste des extensions
        add_filter('plugin_action_links_' . $this->basename, [$this, 'actionLinks']);
        add_action('admin_post_wpfn_check_update', [$this, 'handleManualCheck']);
        add_action('admin_notices', [$this, 'manualCheckNotice']);
    }

    // ------------------------------------------------------------------
    // API GitHub
    // ------------------------------------------------------------------

    /**
     * @return array|null Données normalisées de la dernière release, ou null.
     */
    private function getRelease(bool $force = false): ?array
    {
        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached['release'];
            }
        }

        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $this->owner, $this->repo);
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => $this->apiHeaders('application/vnd.github.full+json'),
        ]);

        $release = null;
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $json = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($json)) {
                $release = $this->normalize($json);
            }
        }

        // On met aussi en cache les échecs (plus brièvement) pour ne pas
        // solliciter GitHub à chaque chargement de page d'administration.
        set_site_transient(self::CACHE_KEY, ['release' => $release], $release ? self::CACHE_TTL : self::ERROR_TTL);

        return $release;
    }

    private function normalize(array $json): ?array
    {
        if (!empty($json['draft']) || !empty($json['prerelease']) || empty($json['tag_name'])) {
            return null;
        }

        $package = null;
        foreach ($json['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? '') === $this->assetName) {
                // Dépôt privé : l'URL publique ne fonctionne pas, on passe par l'API.
                $package = $this->token() ? $asset['url'] : $asset['browser_download_url'];
                break;
            }
        }
        if (!$package) {
            return null; // Pas d'asset construit : on ne propose rien.
        }

        return [
            'version'   => ltrim($json['tag_name'], 'vV'),
            'package'   => $package,
            'url'       => $json['html_url'] ?? $this->repoUrl(),
            'date'      => $json['published_at'] ?? '',
            'changelog' => $json['body_html'] ?? nl2br(esc_html($json['body'] ?? '')),
        ];
    }

    private function apiHeaders(string $accept): array
    {
        $headers = [
            'Accept'               => $accept,
            'User-Agent'           => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
        if ($token = $this->token()) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        return $headers;
    }

    private function token(): string
    {
        return defined('WPFN_GITHUB_TOKEN') ? (string) WPFN_GITHUB_TOKEN : '';
    }

    private function repoUrl(): string
    {
        return sprintf('https://github.com/%s/%s', $this->owner, $this->repo);
    }

    // ------------------------------------------------------------------
    // Intégration WordPress
    // ------------------------------------------------------------------

    public function injectUpdate($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        $release = $this->getRelease();
        if (!$release) {
            return $transient;
        }

        $headers = get_file_data($this->pluginFile, [
            'RequiresWP'  => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        ]);

        $item = (object) [
            'id'            => $this->repoUrl(),
            'slug'          => $this->slug,
            'plugin'        => $this->basename,
            'new_version'   => $release['version'],
            'url'           => $release['url'],
            'package'       => $release['package'],
            'requires'      => $headers['RequiresWP'],
            'requires_php'  => $headers['RequiresPHP'],
            'icons'         => [
                'svg' => WPFN_PLUGIN_URL . 'assets/img/icon.svg',
                '1x'  => WPFN_PLUGIN_URL . 'assets/img/icon1024x1024.png',
            ],
        ];

        if (version_compare($release['version'], $this->version, '>')) {
            $transient->response[$this->basename] = $item;
            unset($transient->no_update[$this->basename]);
        } else {
            // Nécessaire pour afficher le bouton « Activer les mises à jour auto ».
            $item->new_version = $this->version;
            $transient->no_update[$this->basename] = $item;
            unset($transient->response[$this->basename]);
        }

        return $transient;
    }

    /** Fenêtre « Afficher les détails » de la liste des extensions. */
    public function pluginInfo($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== $this->slug) {
            return $result;
        }

        $release = $this->getRelease();
        $data = get_file_data($this->pluginFile, [
            'Name'        => 'Plugin Name',
            'Description' => 'Description',
            'Author'      => 'Author',
            'AuthorURI'   => 'Author URI',
            'RequiresWP'  => 'Requires at least',
            'RequiresPHP' => 'Requires PHP',
        ]);

        return (object) [
            'name'          => $data['Name'],
            'slug'          => $this->slug,
            'version'       => $release['version'] ?? $this->version,
            'author'        => sprintf('<a href="%s">%s</a>', esc_url($data['AuthorURI']), esc_html($data['Author'])),
            'homepage'      => $this->repoUrl(),
            'requires'      => $data['RequiresWP'],
            'requires_php'  => $data['RequiresPHP'],
            'last_updated'  => $release['date'] ?? '',
            'download_link' => $release['package'] ?? '',
            'sections'      => [
                'description' => esc_html($data['Description']),
                'changelog'   => $release ? wp_kses_post($release['changelog']) : '',
            ],
        ];
    }

    /**
     * Garantit que le dossier extrait porte le nom du dossier installé
     * (sinon WordPress installerait le plugin dans un nouveau dossier).
     */
    public function fixSourceFolder($source, $remoteSource, $upgrader, $hookExtra = [])
    {
        global $wp_filesystem;

        if (($hookExtra['plugin'] ?? '') !== $this->basename || is_wp_error($source)) {
            return $source;
        }

        $expected = trailingslashit($remoteSource) . $this->slug . '/';
        if (untrailingslashit($source) === untrailingslashit($expected)) {
            return $source;
        }

        if ($wp_filesystem && $wp_filesystem->move($source, $expected, true)) {
            return $expected;
        }

        return new \WP_Error('wpfn_rename_failed', 'Impossible de renommer le dossier de mise à jour de File Navigator.');
    }

    /** Ajoute l'authentification au téléchargement de l'asset (dépôt privé). */
    public function authorizeDownload($args, $url)
    {
        $token = $this->token();
        if ($token && strpos($url, sprintf('api.github.com/repos/%s/%s/releases/assets/', $this->owner, $this->repo)) !== false) {
            $args['headers'] = array_merge($args['headers'] ?? [], $this->apiHeaders('application/octet-stream'));
        }
        return $args;
    }

    public function forceAutoUpdate($update, $item)
    {
        if (defined('WPFN_FORCE_AUTO_UPDATE') && WPFN_FORCE_AUTO_UPDATE && ($item->plugin ?? '') === $this->basename) {
            return true;
        }
        return $update;
    }

    public function purgeCache($upgrader, $options): void
    {
        if (($options['type'] ?? '') === 'plugin') {
            delete_site_transient(self::CACHE_KEY);
        }
    }

    // ------------------------------------------------------------------
    // Vérification manuelle
    // ------------------------------------------------------------------

    public function actionLinks(array $links): array
    {
        if (current_user_can('update_plugins')) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=wpfn_check_update'), 'wpfn_check_update');
            $links[] = sprintf('<a href="%s">%s</a>', esc_url($url), 'Vérifier les mises à jour');
        }
        return $links;
    }

    public function handleManualCheck(): void
    {
        if (!current_user_can('update_plugins')) {
            wp_die('Permission refusée.');
        }
        check_admin_referer('wpfn_check_update');

        delete_site_transient(self::CACHE_KEY);
        $release = $this->getRelease(true);
        delete_site_transient('update_plugins');
        wp_update_plugins();

        if (!$release) {
            $status = 'error';
        } elseif (version_compare($release['version'], $this->version, '>')) {
            $status = 'available';
        } else {
            $status = 'uptodate';
        }

        wp_safe_redirect(add_query_arg('wpfn_update', $status, self_admin_url('plugins.php')));
        exit;
    }

    public function manualCheckNotice(): void
    {
        $status = isset($_GET['wpfn_update']) ? sanitize_key($_GET['wpfn_update']) : '';
        $messages = [
            'available' => ['warning', 'File Navigator : une nouvelle version est disponible.'],
            'uptodate'  => ['success', 'File Navigator est à jour (version ' . esc_html($this->version) . ').'],
            'error'     => ['error', 'File Navigator : impossible de récupérer la dernière release sur GitHub (aucune release publiée avec l\'asset ' . esc_html($this->assetName) . ', ou API indisponible).'],
        ];
        if (isset($messages[$status])) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $messages[$status][0], $messages[$status][1]);
        }
    }
}
