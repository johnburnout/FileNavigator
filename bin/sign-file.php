<?php
/**
 * Signe un fichier avec la clé privée Ed25519 et écrit la signature (base64) sur la sortie.
 * Vérifie au passage que la clé correspond à GitHubUpdater::PUBLIC_KEY
 * (sauf WPFN_KEY_ROTATION=1, pour la version de transition d'une rotation de clé).
 *
 * Usage : php bin/sign-file.php <clé privée> <fichier>
 */
[, $keyPath, $file] = $argv + [null, null, null];
if (!$keyPath || !$file || !is_readable($keyPath) || !is_readable($file)) {
    fwrite(STDERR, "Usage : php bin/sign-file.php <clé privée> <fichier>\n");
    exit(1);
}
require __DIR__ . '/../src/Updater/GitHubUpdater.php';

$secret = base64_decode(trim(file_get_contents($keyPath)), true);
if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    fwrite(STDERR, "Clé privée invalide : $keyPath\n");
    exit(1);
}
$public = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret));
if ($public !== \WPFN\Updater\GitHubUpdater::PUBLIC_KEY) {
    // Rotation de clé : la version de transition contient la nouvelle clé publique mais doit être
    // signée avec l'ancienne (celle que connaissent les sites installés). Refus sauf demande explicite.
    if (getenv('WPFN_KEY_ROTATION') !== '1') {
        fwrite(STDERR, "Cette clé ne correspond pas à GitHubUpdater::PUBLIC_KEY : signature refusée.\n"
            . "(Version de transition d'une rotation de clé : relancer avec WPFN_KEY_ROTATION=1.)\n");
        exit(1);
    }
    fwrite(STDERR, "Rotation de clé : signature avec l'ancienne clé ($public).\n");
}
$data = file_get_contents($file);
$signature = sodium_crypto_sign_detached($data, $secret);
if (!\WPFN\Updater\GitHubUpdater::verifySignature($data, $signature, $public)) {
    fwrite(STDERR, "La vérification de la signature a échoué.\n");
    exit(1);
}
echo base64_encode($signature), "\n";
