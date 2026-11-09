<?php
namespace WPFN\Tests;

use WPFN\Updater\GitHubUpdater;

final class GitHubUpdaterTest extends TestCase
{
    public function testSignatureValide(): void
    {
        $pair = sodium_crypto_sign_keypair();
        $public = base64_encode(sodium_crypto_sign_publickey($pair));
        $signature = sodium_crypto_sign_detached('paquet', sodium_crypto_sign_secretkey($pair));

        $this->assertTrue(GitHubUpdater::verifySignature('paquet', $signature, $public));
        $this->assertFalse(GitHubUpdater::verifySignature('paquet modifié', $signature, $public));
    }

    public function testSignatureDUneAutreCleRefusee(): void
    {
        $autre = sodium_crypto_sign_keypair();
        $signature = sodium_crypto_sign_detached('paquet', sodium_crypto_sign_secretkey($autre));
        $this->assertFalse(GitHubUpdater::verifySignature('paquet', $signature), 'clé publique du plugin');
    }

    public function testSignatureMalformeeRefusee(): void
    {
        $this->assertFalse(GitHubUpdater::verifySignature('paquet', ''));
        $this->assertFalse(GitHubUpdater::verifySignature('paquet', 'trop courte'));
        $this->assertFalse(GitHubUpdater::verifySignature('paquet', str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES)));
    }

    public function testClePubliqueValide(): void
    {
        $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen(base64_decode(GitHubUpdater::PUBLIC_KEY, true)));
    }

    public function testReleaseSansSignatureIgnoree(): void
    {
        $updater = new GitHubUpdater(WPFN_PLUGIN_DIR . 'filenavigator.php', 'owner', 'repo');
        $release = ['tag_name' => 'v9.9.9', 'assets' => [
            ['name' => 'filenavigator.zip', 'browser_download_url' => 'https://x/filenavigator.zip', 'url' => 'https://api/1'],
        ]];
        $this->assertNull(self::invoke($updater, 'normalize', $release));

        $release['assets'][] = ['name' => 'filenavigator.zip.sig', 'browser_download_url' => 'https://x/filenavigator.zip.sig', 'url' => 'https://api/2'];
        $normalized = self::invoke($updater, 'normalize', $release);
        $this->assertSame('9.9.9', $normalized['version']);
        $this->assertSame('https://x/filenavigator.zip.sig', $normalized['signature']);
    }

    public function testBrouillonEtPreversionIgnores(): void
    {
        $updater = new GitHubUpdater(WPFN_PLUGIN_DIR . 'filenavigator.php', 'owner', 'repo');
        $this->assertNull(self::invoke($updater, 'normalize', ['tag_name' => 'v2', 'draft' => true]));
        $this->assertNull(self::invoke($updater, 'normalize', ['tag_name' => 'v2', 'prerelease' => true]));
    }
}
