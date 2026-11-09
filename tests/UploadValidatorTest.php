<?php
namespace WPFN\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use WPFN\FileNavigator\UploadValidator;

final class UploadValidatorTest extends TestCase
{
    private const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private UploadValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new UploadValidator([
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'pdf'          => 'application/pdf',
            'txt'          => 'text/plain',
            'php'          => 'application/x-php', // même autorisé par un filtre, reste bloqué
        ]);
    }

    #[DataProvider('nomsAcceptes')]
    public function testNomsAcceptes(string $name): void
    {
        $this->validator->checkName($name);
        $this->addToAssertionCount(1);
    }

    public static function nomsAcceptes(): array
    {
        return [['photo.jpg'], ['PHOTO.JPEG'], ['rapport final.pdf'], ['notes.v2.txt']];
    }

    #[DataProvider('nomsRefuses')]
    public function testNomsRefuses(string $name): void
    {
        $this->expectException(\RuntimeException::class);
        $this->validator->checkName($name);
    }

    public static function nomsRefuses(): array
    {
        return [
            'sans extension'     => ['README'],
            'point final'        => ['image.'],
            'php'                => ['shell.php'],
            'PHP majuscules'     => ['shell.PHP'],
            'double extension'   => ['image.php.jpg'],
            'phtml'              => ['x.phtml'],
            'htaccess'           => ['.htaccess'],
            'svg'                => ['logo.svg'],
            'html'               => ['page.html'],
            'non autorisée'      => ['archive.zip'],
        ];
    }

    public function testExtensionsAutoriseesSansExtensionsBloquees(): void
    {
        $this->assertSame(['jpe', 'jpeg', 'jpg', 'pdf', 'png', 'txt'], $this->validator->allowedExtensions());
    }

    public function testImageValideAcceptee(): void
    {
        $path = $this->write('in/ok.png', base64_decode(self::PNG_1PX));
        $this->validator->checkContent($path, 'ok.png');
        $this->addToAssertionCount(1);
    }

    public function testTexteDeguiseEnImageRefuse(): void
    {
        $path = $this->write('in/faux.png', 'ceci n\'est pas une image');
        $this->expectException(\RuntimeException::class);
        $this->validator->checkContent($path, 'faux.png');
    }

    public function testImagePolyglotteRefusee(): void
    {
        $path = $this->write('in/poly.png', base64_decode(self::PNG_1PX) . '<?php system($_GET["c"]); ?>');
        $this->expectExceptionMessage('contenu suspect');
        $this->validator->checkContent($path, 'poly.png');
    }

    public function testWebpAjouteParDefaut(): void
    {
        $this->assertContains('webp', (new UploadValidator(UploadValidator::defaultMimes()))->allowedExtensions());
    }
}
