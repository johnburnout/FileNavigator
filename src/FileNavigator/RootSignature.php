<?php
namespace WPFN\FileNavigator;

/**
 * Signature du dossier racine : le navigateur renvoie le chemin reçu du shortcode,
 * le serveur vérifie qu'il n'a pas été modifié (sinon n'importe quel dossier du serveur
 * serait accessible en changeant le paramètre « root »).
 * L'option « public_download » fait partie de la signature.
 */
final class RootSignature
{
    public static function sign(string $root, bool $publicDownload = false): string
    {
        return hash_hmac('sha256', 'WPFN_root|' . ($publicDownload ? '1' : '0') . '|' . $root, wp_salt('nonce'));
    }

    public static function verify(string $root, bool $publicDownload, string $signature): bool
    {
        return $signature !== '' && hash_equals(self::sign($root, $publicDownload), $signature);
    }
}
