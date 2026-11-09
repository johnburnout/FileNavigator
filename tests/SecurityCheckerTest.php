<?php
namespace WPFN\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use WPFN\FileNavigator\SecurityChecker;

final class SecurityCheckerTest extends TestCase
{
    private SecurityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new SecurityChecker($this->uploads . '/docs');
    }

    private function safe(string $relative): bool
    {
        return $this->checker->isPathSafe($this->checker->resolve($relative));
    }

    public function testCheminsInterieursAcceptes(): void
    {
        $this->assertTrue($this->safe(''));
        $this->assertTrue($this->safe('.'));
        $this->assertTrue($this->safe('a.txt'));
        $this->assertTrue($this->safe('sous/b.txt'));
        $this->assertTrue($this->safe('sous/../a.txt'));
    }

    #[DataProvider('cheminsSortants')]
    public function testCheminsSortantsRefuses(string $relative): void
    {
        $this->assertFalse($this->safe($relative));
    }

    public static function cheminsSortants(): array
    {
        return [
            'parent'            => ['..'],
            'remontée'          => ['../../secret/s.txt'],
            'absolu'            => ['/etc/passwd'],
            'schéma'            => ['x://../../secret/s.txt'],
            'phar'              => ['phar://../secret'],
            'inexistant'        => ['absent.txt'],
            'préfixe trompeur'  => ['../docs-evil'],
        ];
    }

    public function testLienSymboliqueSortantRefuse(): void
    {
        symlink($this->tmp . '/secret', $this->uploads . '/docs/fuite');
        $this->assertFalse($this->safe('fuite'));
        $this->assertFalse($this->safe('fuite/s.txt'));
    }

    public function testDossierVoisinAuNomProcheRefuse(): void
    {
        $this->write('uploads/docs-evil/x.txt', 'x');
        $this->assertFalse($this->safe('../docs-evil/x.txt'));
    }
}
