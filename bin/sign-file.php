<?php
/**
 * Signe un fichier avec la clé privée Ed25519 et écrit la signature (base64) sur la sortie.
 * Vérifie au passage que la clé correspond à GitHubUpdater::PUBLIC_KEY.
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
if (base64_encode(sodium_crypto_sign_publickey_from_secretkey($secret)) !== \WPFN\Updater\GitHubUpdater::PUBLIC_KEY) {
    fwrite(STDERR, "Cette clé ne correspond pas à GitHubUpdater::PUBLIC_KEY : signature refusée.\n");
    exit(1);
}
$data = file_get_contents($file);
$signature = sodium_crypto_sign_detached($data, $secret);
if (!\WPFN\Updater\GitHubUpdater::verifySignature($data, $signature)) {
    fwrite(STDERR, "La vérification de la signature a échoué.\n");
    exit(1);
}
echo base64_encode($signature), "\n";
