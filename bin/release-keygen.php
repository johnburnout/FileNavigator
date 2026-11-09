<?php
/**
 * Génère la paire de clés Ed25519 qui signe les releases de File Navigator.
 * La clé privée reste sur cette machine (jamais dans le dépôt) ; la clé publique
 * affichée doit être recopiée dans GitHubUpdater::PUBLIC_KEY.
 *
 * Usage : php bin/release-keygen.php [chemin de la clé privée]
 */
$path = $argv[1] ?? (getenv('HOME') . '/.config/filenavigator/release-signing.key');
if (file_exists($path)) {
    fwrite(STDERR, "La clé $path existe déjà : refus de l'écraser.\n");
    exit(1);
}
if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) {
    fwrite(STDERR, "Impossible de créer " . dirname($path) . "\n");
    exit(1);
}
$pair = sodium_crypto_sign_keypair();
umask(077);
file_put_contents($path, base64_encode(sodium_crypto_sign_secretkey($pair)) . "\n");
chmod($path, 0600);
echo "Clé privée : $path (à sauvegarder hors de cette machine)\n";
echo "Clé publique : " . base64_encode(sodium_crypto_sign_publickey($pair)) . "\n";
