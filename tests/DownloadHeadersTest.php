<?php
namespace WPFN\Tests;

use WPFN\FileNavigator\FileOperationService as F;

final class DownloadHeadersTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = $this->write('uploads/docs/rapport été.pdf', str_repeat('x', 1234));
    }

    public function testEnvoiParPhpParDefaut(): void
    {
        $h = F::downloadHeaders($this->file, 'rapport "été".pdf', 'application/octet-stream');
        $this->assertSame('1234', $h['Content-Length']);
        $this->assertSame('attachment; filename="rapport ___t___.pdf"; filename*=UTF-8\'\'rapport%20%22%C3%A9t%C3%A9%22.pdf', $h['Content-Disposition']);
        $this->assertSame('nosniff', $h['X-Content-Type-Options']);
        $this->assertArrayNotHasKey('X-Sendfile', $h);
    }

    public function testXSendfile(): void
    {
        $h = F::downloadHeaders($this->file, 'r.pdf', 'application/octet-stream', 'x-sendfile');
        $this->assertSame($this->file, $h['X-Sendfile']);
        $this->assertArrayNotHasKey('Content-Length', $h, 'le serveur web calcule la taille');
    }

    public function testXAccelRedirect(): void
    {
        $h = F::downloadHeaders($this->file, 'r.pdf', 'application/octet-stream', 'x-accel-redirect', [$this->uploads => '/interne/uploads/']);
        $this->assertSame('/interne/uploads/docs/rapport%20%C3%A9t%C3%A9.pdf', $h['X-Accel-Redirect']);
    }

    public function testXAccelRedirectHorsCorrespondanceRetombeSurPhp(): void
    {
        $h = F::downloadHeaders($this->file, 'r.pdf', 'application/octet-stream', 'x-accel-redirect', ['/autre/chemin' => '/interne/']);
        $this->assertArrayNotHasKey('X-Accel-Redirect', $h);
        $this->assertSame('1234', $h['Content-Length']);
    }

    public function testPrefixeProcheNonConfondu(): void
    {
        $h = F::downloadHeaders($this->file, 'r.pdf', 'application/octet-stream', 'x-accel-redirect', [$this->tmp . '/upl' => '/interne/']);
        $this->assertArrayNotHasKey('X-Accel-Redirect', $h, '« /upl » ne doit pas correspondre à « /uploads »');
    }

    public function testModeInconnuRetombeSurPhp(): void
    {
        $h = F::downloadHeaders($this->file, 'r.pdf', 'application/octet-stream', 'n-importe-quoi');
        $this->assertSame('1234', $h['Content-Length']);
    }
}
