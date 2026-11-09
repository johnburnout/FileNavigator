<?php
namespace WPFN\Tests;

use WPFN\FileNavigator\RootSignature;

final class RootSignatureTest extends TestCase
{
    public function testResolutionDansLaBase(): void
    {
        $this->assertSame(realpath($this->uploads), RootSignature::resolve(''));
        $this->assertSame(realpath($this->uploads . '/docs'), RootSignature::resolve('docs'));
        $this->assertSame('docs', RootSignature::relative(RootSignature::resolve('docs')));
        $this->assertSame('', RootSignature::relative(RootSignature::resolve('')));
    }

    public function testResolutionHorsBaseRefusee(): void
    {
        $this->assertNull(RootSignature::resolve('../secret'));
        $this->assertNull(RootSignature::resolve('docs/a.txt'), 'un fichier n\'est pas une racine');
        $this->assertNull(RootSignature::resolve('absent'));
        symlink($this->tmp . '/secret', $this->uploads . '/fuite');
        $this->assertNull(RootSignature::resolve('fuite'));
    }

    public function testVerificationDeLaSignature(): void
    {
        $sig = RootSignature::sign('docs', true);
        $this->assertTrue(RootSignature::verify('docs', true, $sig));
        $this->assertFalse(RootSignature::verify('docs', false, $sig), 'public_download fait partie de la signature');
        $this->assertFalse(RootSignature::verify('docs/sous', true, $sig));
        $this->assertFalse(RootSignature::verify('docs', true, ''));
    }

    public function testSignatureValideMaisHorsBaseRefusee(): void
    {
        $this->assertFalse(RootSignature::verify('../secret', false, RootSignature::sign('../secret', false)));
    }

    public function testFiltreDeBase(): void
    {
        $GLOBALS['wpfn_test']['filters']['WPFN_root_base'] = $this->tmp . '/secret';
        $this->assertSame(realpath($this->tmp . '/secret'), RootSignature::allowedBase());
        $this->assertTrue(RootSignature::isAllowed($this->tmp . '/secret/s.txt'));
        $this->assertFalse(RootSignature::isAllowed($this->uploads));
    }
}
