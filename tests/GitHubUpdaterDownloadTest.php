<?php
namespace WPFN\Tests;

use WPFN\Updater\GitHubUpdater;

/**
 * Téléchargement vérifié : la signature doit être trouvée et contrôlée même si l'API GitHub
 * est indisponible au moment de l'installation (cas réel de la mise à jour 1.3.6).
 */
final class GitHubUpdaterDownloadTest extends TestCase
{
    private const PKG = 'https://github.com/owner/repo/releases/download/v9.9.9/filenavigator.zip';
    private const API = 'https://api.github.com/repos/owner/repo/releases/latest';

    private GitHubUpdater $updater;
    private string $basename;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->updater = new GitHubUpdater(WPFN_PLUGIN_DIR . 'filenavigator.php', 'owner', 'repo');
        $this->basename = plugin_basename(WPFN_PLUGIN_DIR . 'filenavigator.php');
        $GLOBALS['wpfn_test']['transients'] = [];
        $GLOBALS['wpfn_test']['requests'] = [];
        $GLOBALS['wpfn_test']['http'] = [
            self::API => ['code' => 403, 'body' => '{"message":"API rate limit exceeded"}'],
            self::PKG => ['code' => 200, 'body' => 'contenu du paquet'],
        ];
    }

    private function download(?array $hookExtra = null)
    {
        return $this->updater->verifiedDownload(false, self::PKG, null, $hookExtra ?? ['plugin' => $this->basename]);
    }

    public function testSignatureTrouveeSansAPI(): void
    {
        // API en échec (403) et cache vide : la signature est cherchée à côté du paquet
        $GLOBALS['wpfn_test']['http'][self::PKG . '.sig'] = ['code' => 200, 'body' => base64_encode(str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES))];
        $result = $this->download();
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('wpfn_bad_signature', $result->get_error_code(), 'la signature a bien été trouvée puis vérifiée');
        $this->assertNotContains(self::API, $GLOBALS['wpfn_test']['requests'], 'aucun appel à l\'API nécessaire');
    }

    public function testSignatureAbsenteRefusee(): void
    {
        $result = $this->download();
        $this->assertSame('wpfn_signature_unavailable', $result->get_error_code());
        $this->assertNotContains(self::PKG, $GLOBALS['wpfn_test']['requests'], 'le paquet n\'est pas téléchargé sans signature');
    }

    public function testPaquetDUnAutrePluginIgnore(): void
    {
        $this->assertFalse($this->updater->verifiedDownload(false, 'https://downloads.wordpress.org/plugin/akismet.zip', null, ['plugin' => 'akismet/akismet.php']));
    }

    public function testPaquetDeCeDepotReconnuSansHookExtra(): void
    {
        $result = $this->download([]);
        $this->assertInstanceOf(\WP_Error::class, $result, 'reconnu par son URL, donc vérifié');
    }

    public function testArchiveLocaleNonBloquee(): void
    {
        $this->assertFalse($this->updater->verifiedDownload(false, '/tmp/filenavigator.zip', null, ['plugin' => $this->basename]));
    }

    public function testDepotPriveSansAPIRefuseProprement(): void
    {
        $result = $this->updater->verifiedDownload(false, 'https://api.github.com/repos/owner/repo/releases/assets/1', null, ['plugin' => $this->basename]);
        $this->assertSame('wpfn_unknown_package', $result->get_error_code());
    }

    public function testCacheConserveApresMiseAJourDUneAutreExtension(): void
    {
        $GLOBALS['wpfn_test']['transients']['wpfn_github_release_v2'] = ['release' => null];
        $this->updater->purgeCache(null, ['type' => 'plugin', 'action' => 'update', 'plugins' => ['akismet/akismet.php']]);
        $this->assertArrayHasKey('wpfn_github_release_v2', $GLOBALS['wpfn_test']['transients']);
        $this->updater->purgeCache(null, ['type' => 'plugin', 'action' => 'update', 'plugins' => [$this->basename]]);
        $this->assertArrayNotHasKey('wpfn_github_release_v2', $GLOBALS['wpfn_test']['transients']);
    }
}
