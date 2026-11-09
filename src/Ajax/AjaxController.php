<?php
namespace WPFN\Ajax;

use WPFN\FileNavigator\FileOperationService;
use WPFN\FileNavigator\RootSignature;

/**
 * Points d'entrée AJAX du plugin (actions « WPFN_* » de admin-ajax.php).
 */
final class AjaxController
{
    public const NONCE_ACTION = 'WPFN_ajax_nonce';

    /** action AJAX => méthode */
    private const ACTIONS = [
        'WPFN_upload'         => 'upload',
        'WPFN_check_exists'   => 'checkExists',
        'WPFN_prepare_upload' => 'prepareUpload',
        'WPFN_download'       => 'download',
        'WPFN_delete'         => 'delete',
        'WPFN_rename'         => 'rename',
        'WPFN_paste'          => 'paste',
        'WPFN_mkdir'          => 'mkdir',
    ];

    public static function register(): void
    {
        foreach (self::ACTIONS as $action => $method) {
            add_action('wp_ajax_' . $action, [self::class, $method]);
        }
        // Téléchargement autorisé aux visiteurs non connectés (selon public_download)
        add_action('wp_ajax_nopriv_WPFN_download', [self::class, 'download']);
    }

    // ---------- UPLOAD ----------
    public static function upload(): void
    {
        self::handle('upload_files', function () {
            $paths = self::json('paths');      // chemins relatifs (glisser-déposer de dossiers)
            $dirs = self::json('dirs') ?? []; // dossiers vides à créer
            $files = $_FILES['files'] ?? ['tmp_name' => []];
            // ['files' => [...], 'rejected' => [['path', 'reason'], ...]]
            return self::service()->upload($files, self::currentDir(), $paths, $dirs);
        });
    }

    // ---------- UPLOAD : détection des conflits ----------
    public static function checkExists(): void
    {
        self::handle('upload_files', function () {
            $names = self::json('names') ?? [];
            return ['existing' => self::service()->findExisting($names, self::currentDir())];
        });
    }

    // ---------- UPLOAD : application des choix (remplacer / fusionner / garder les deux) ----------
    public static function prepareUpload(): void
    {
        self::handle('upload_files', function () {
            $items = self::json('items') ?? [];
            // Remplacer ou fusionner modifie/supprime l'existant : droit de suppression requis
            foreach ($items as $item) {
                if (in_array($item['action'] ?? 'new', ['replace', 'merge'], true) && !current_user_can('delete_posts')) {
                    throw new \RuntimeException('Permission refusée : vous ne pouvez pas remplacer ou fusionner des éléments existants.');
                }
            }
            $mapping = self::service()->prepareUpload($items, self::currentDir());
            return ['mapping' => (object) $mapping];
        });
    }

    // ---------- DOWNLOAD (public) ----------
    public static function download(): void
    {
        // Pas de nonce ni de capacité : lien direct, ouvert aux visiteurs si public_download
        // Seuls les chemins de type chaîne sont retenus (évite un TypeError sur entrée forgée)
        $paths = array_values(array_filter(self::json('paths', $_GET) ?? [], 'is_string'));
        if (empty($paths)) {
            wp_die('Aucun fichier sélectionné.');
        }

        [$root, $publicDownload] = self::root(false);
        if (!$publicDownload && !current_user_can('read')) {
            wp_die(esc_html('Connectez-vous pour télécharger ce fichier.'), '', ['response' => 403]);
        }

        try {
            (new FileOperationService($root))->download($paths);
        } catch (\Exception $e) {
            wp_die(esc_html($e->getMessage()));
        }
    }

    // ---------- DELETE ----------
    public static function delete(): void
    {
        self::handle('delete_posts', function () {
            $paths = self::json('paths');
            if (empty($paths)) {
                throw new \RuntimeException('Aucun élément sélectionné.');
            }
            self::service()->delete($paths);
            return null;
        });
    }

    // ---------- RENAME ----------
    public static function rename(): void
    {
        self::handle('edit_posts', function () {
            $old = self::text('old_path');
            $newName = self::text('new_name');
            if ($old === '' || $newName === '') {
                throw new \RuntimeException('Paramètres manquants.');
            }
            self::service()->rename($old, $newName);
            return null;
        });
    }

    // ---------- PASTE ----------
    public static function paste(): void
    {
        self::handle('edit_posts', function () {
            $paths = self::json('paths');
            $targetDir = self::text('target_dir');
            if (empty($paths) || $targetDir === '') {
                throw new \RuntimeException('Paramètres manquants.');
            }
            $mode = self::text('mode') ?: 'copy';
            self::service()->paste($paths, self::text('source_dir'), $targetDir, $mode);
            return null;
        });
    }

    // ---------- MKDIR ----------
    public static function mkdir(): void
    {
        self::handle('edit_posts', function () {
            $newDir = self::text('new_dir');
            if ($newDir === '') {
                throw new \RuntimeException('Nom du dossier manquant.');
            }
            self::service()->createDirectory($newDir, self::currentDir());
            return ['message' => 'Dossier créé avec succès.'];
        });
    }

    // ================================
    // Helpers
    // ================================

    /**
     * Enveloppe commune : nonce, capacité, puis réponse JSON (succès ou message d'erreur).
     */
    private static function handle(string $capability, callable $action): void
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'Permission refusée.']);
        }
        try {
            $data = $action();
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
            return;
        }
        wp_send_json_success($data);
    }

    private static function service(): FileOperationService
    {
        return new FileOperationService(self::root()[0]);
    }

    /**
     * Dossier racine de la requête (après vérification de sa signature) et option public_download.
     * Sans « root », on utilise le dossier uploads de WordPress.
     * @return array{0: string, 1: bool}
     */
    private static function root(bool $json = true): array
    {
        $src = $json ? $_POST : $_GET;
        $root = isset($src['root']) && is_string($src['root']) ? wp_unslash($src['root']) : '';
        $sig = isset($src['root_sig']) && is_string($src['root_sig']) ? wp_unslash($src['root_sig']) : '';
        $publicDownload = isset($src['root_pd']) && $src['root_pd'] === '1';

        if ($root === '') {
            return [wp_upload_dir()['basedir'], false];
        }
        if (!RootSignature::verify($root, $publicDownload, $sig)) {
            $message = 'Dossier racine non autorisé.';
            if ($json) {
                wp_send_json_error(['message' => $message], 403);
            }
            wp_die(esc_html($message), '', ['response' => 403]);
        }
        return [$root, $publicDownload];
    }

    private static function currentDir(): string
    {
        return self::text('current_dir');
    }

    /** Champ texte de $_POST, déséchappé et nettoyé ('' si absent). */
    private static function text(string $key): string
    {
        // Un paramètre envoyé en tableau (key[]=...) est ignoré
        return isset($_POST[$key]) && is_string($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    /** Champ JSON (tableau) de $_POST ou de $src ; null si absent ou invalide. */
    private static function json(string $key, ?array $src = null): ?array
    {
        $src ??= $_POST;
        if (!isset($src[$key]) || !is_string($src[$key])) {
            return null;
        }
        $value = json_decode(wp_unslash($src[$key]), true);
        return is_array($value) ? $value : null;
    }
}
