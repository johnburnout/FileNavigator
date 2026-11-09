<?php
namespace WPFN\FileNavigator;

/**
 * Signature du dossier racine : le navigateur renvoie le chemin reçu du shortcode,
 * le serveur vérifie qu'il n'a pas été modifié (sinon n'importe quel dossier du serveur
 * serait accessible en changeant le paramètre « root »).
 * L'option « public_download » fait partie de la signature.
 *
 * Le dossier racine doit en outre se trouver sous la base autorisée (wp-content/uploads
 * par défaut, modifiable via le filtre « WPFN_root_base ») : sans cela, un auteur pourrait
 * faire signer n'importe quel dossier du serveur via l'attribut « root » du shortcode.
 */
final class RootSignature
{
    public static function sign(string $root, bool $publicDownload = false): string
    {
        return hash_hmac('sha256', 'WPFN_root|' . ($publicDownload ? '1' : '0') . '|' . $root, wp_salt('nonce'));
    }

    /**
     * $root est relatif à la base autorisée ('' = la base) : le chemin absolu du serveur
     * n'est ainsi jamais exposé dans la page.
     */
    public static function verify(string $root, bool $publicDownload, string $signature): bool
    {
        return $signature !== ''
            && hash_equals(self::sign($root, $publicDownload), $signature)
            && self::resolve($root) !== null;
    }

    /** Chemin réel d'une racine relative à la base, ou null si elle n'existe pas ou en sort. */
    public static function resolve(string $relativeRoot): ?string
    {
        $base = self::allowedBase();
        $real = realpath($relativeRoot === '' ? $base : $base . '/' . ltrim($relativeRoot, '/'));
        return $real !== false && is_dir($real) && self::isAllowed($real) ? $real : null;
    }

    /** Chemin d'un dossier (déjà vérifié par isAllowed()) relatif à la base ('' pour la base). */
    public static function relative(string $path): string
    {
        $real = realpath($path) ?: $path;
        return ltrim(substr($real, strlen(self::allowedBase())), '/');
    }

    /** Dossier sous lequel toute racine doit se trouver (chemin réel, sans « / » final). */
    public static function allowedBase(): string
    {
        $base = (string) apply_filters('WPFN_root_base', wp_upload_dir()['basedir']);
        $real = realpath($base);
        return rtrim($real !== false ? $real : $base, '/');
    }

    /** Le chemin existe et se trouve sous la base autorisée (ou est la base elle-même). */
    public static function isAllowed(string $path): bool
    {
        $real = realpath($path);
        if ($real === false) {
            return false;
        }
        $base = self::allowedBase();
        return $base !== '' && ($real === $base || str_starts_with($real, $base . '/'));
    }
}
