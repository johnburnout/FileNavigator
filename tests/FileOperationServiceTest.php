<?php
namespace WPFN\Tests;

use WPFN\FileNavigator\FileOperationService;
use WPFN\FileNavigator\UploadValidator;

final class FileOperationServiceTest extends TestCase
{
    private FileOperationService $service;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = $this->uploads . '/docs';
        $this->service = new FileOperationService($this->root, new UploadValidator(['txt' => 'text/plain', 'jpg' => 'image/jpeg']));
    }

    // ---------- Racine protégée ----------

    public function testSuppressionDeLaRacineRefusee(): void
    {
        foreach (['', '.', 'sous/..'] as $path) {
            try {
                $this->service->delete([$path]);
                $this->fail("delete('$path') aurait dû être refusé");
            } catch (\RuntimeException $e) {
                $this->assertDirectoryExists($this->root);
            }
        }
    }

    public function testRenommageDeLaRacineRefuse(): void
    {
        $this->expectExceptionMessage('racine');
        $this->service->rename('', 'autre');
    }

    public function testSuppressionHorsRacineRefusee(): void
    {
        $this->expectException(\RuntimeException::class);
        try {
            $this->service->delete(['../../secret/s.txt']);
        } finally {
            $this->assertFileExists($this->tmp . '/secret/s.txt');
        }
    }

    public function testSuppressionNormale(): void
    {
        $this->service->delete(['a.txt', 'sous']);
        $this->assertFileDoesNotExist($this->root . '/a.txt');
        $this->assertDirectoryDoesNotExist($this->root . '/sous');
    }

    // ---------- Renommage ----------

    public function testRenommageNormal(): void
    {
        $this->service->rename('a.txt', 'c.txt');
        $this->assertFileExists($this->root . '/c.txt');
    }

    public function testRenommageVersExtensionInterditeRefuse(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->rename('a.txt', 'a.php');
    }

    public function testRenommageAvecSeparateurRefuse(): void
    {
        $this->expectExceptionMessage('Nom invalide');
        $this->service->rename('a.txt', '../a.txt');
    }

    // ---------- Copier / coller ----------

    public function testCopieDUnDossierDansLuiMemeRefusee(): void
    {
        $this->expectExceptionMessage('dans lui-même');
        $this->service->paste(['sous'], 'sous', 'copy');
    }

    public function testDeplacementDUnDossierDansSonSousDossierRefuse(): void
    {
        mkdir($this->root . '/sous/profond');
        $this->expectExceptionMessage('sous-dossiers');
        $this->service->paste(['sous'], 'sous/profond', 'cut');
    }

    public function testCopieAvecNomUnique(): void
    {
        $this->service->paste(['a.txt'], '.', 'copy');
        $this->assertFileExists($this->root . '/a_1.txt');
        $this->service->paste(['a.txt'], 'sous', 'cut');
        $this->assertFileExists($this->root . '/sous/a.txt');
        $this->assertFileDoesNotExist($this->root . '/a.txt');
    }

    // ---------- Préparation de l'upload ----------

    public function testPreparationGarderLesDeux(): void
    {
        $mapping = $this->service->prepareUpload([
            ['name' => 'a.txt', 'isDir' => false, 'action' => 'keep'],
            ['name' => 'a_1.txt', 'isDir' => false, 'action' => 'new'],
            ['name' => 'nouveau.txt', 'isDir' => false, 'action' => 'new'],
        ], '');
        $this->assertSame(['a.txt' => 'a_2.txt', 'a_1.txt' => 'a_1.txt', 'nouveau.txt' => 'nouveau.txt'], $mapping);
    }

    public function testPreparationRemplacerUnDossier(): void
    {
        $this->service->prepareUpload([['name' => 'sous', 'isDir' => true, 'action' => 'replace']], '');
        $this->assertDirectoryDoesNotExist($this->root . '/sous');
    }

    public function testPreparationFusionFichierRefusee(): void
    {
        $this->expectExceptionMessage('fusionner');
        $this->service->prepareUpload([['name' => 'a.txt', 'isDir' => false, 'action' => 'merge']], '');
    }

    public function testPreparationNomDangereuxRefuse(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->prepareUpload([['name' => '../evil', 'isDir' => true, 'action' => 'new']], '');
    }

    public function testElementsExistants(): void
    {
        $this->assertSame(
            [['name' => 'a.txt', 'isDir' => false], ['name' => 'sous', 'isDir' => true]],
            $this->service->findExisting(['a.txt', 'sous', 'absent.txt', 'a.txt'], '')
        );
    }

    // ---------- Création de dossier ----------

    public function testCreationDeDossier(): void
    {
        $this->service->createDirectory('nouveau', 'sous');
        $this->assertDirectoryExists($this->root . '/sous/nouveau');
    }

    public function testCreationDeDossierHorsRacineRefusee(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->createDirectory('x', '../../secret');
    }

    public function testCreationDeDossierAvecSeparateurRefusee(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->createDirectory('../x', '');
    }

    // ---------- Téléchargement groupé ----------

    public function testLimitesDuZip(): void
    {
        $GLOBALS['wpfn_test']['filters']['WPFN_zip_limits'] = ['max_size' => 1000, 'max_files' => 1];
        $this->expectExceptionMessage('trop volumineuse');
        $this->service->download(['a.txt', 'sous']);
    }

    public function testZipHorsRacineRefuse(): void
    {
        $this->expectExceptionMessage('Chemin non autorisé');
        $this->service->download(['a.txt', '../../secret']);
    }
}
