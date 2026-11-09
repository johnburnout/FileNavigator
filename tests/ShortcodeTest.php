<?php
namespace WPFN\Tests;

use WPFN\Shortcode\FileNavigatorShortcode as Shortcode;

final class ShortcodeTest extends TestCase
{
    public function testRacineDuShortcode(): void
    {
        $this->assertSame(realpath($this->uploads . '/docs'), self::invoke(Shortcode::class, 'getRootPath', 'docs'));
        $this->assertSame(realpath($this->uploads . '/docs'), self::invoke(Shortcode::class, 'getRootPath', $this->uploads . '/docs'));
        foreach (['/', '/etc', '../secret', $this->tmp . '/secret', 'absent', 'docs/a.txt'] as $root) {
            $this->assertSame('', self::invoke(Shortcode::class, 'getRootPath', $root), "root=\"$root\" doit être refusé");
        }
    }

    public function testCouleurs(): void
    {
        foreach (['#fff', '#A1B2C3', 'rgba(0, 0, 0, .5)', 'hsl(120 50% 50%)', 'lightblue'] as $ok) {
            $this->assertSame($ok, self::invoke(Shortcode::class, 'cssColor', $ok));
        }
        foreach (['red;position:fixed', 'url(x)', 'expression(alert(1))', '#ggg', 'red}'] as $ko) {
            $this->assertSame('', self::invoke(Shortcode::class, 'cssColor', $ko), $ko);
        }
    }

    public function testDimensions(): void
    {
        foreach (['800px', '100%', '80vh', '12.5rem', 'auto'] as $ok) {
            $this->assertSame($ok, self::invoke(Shortcode::class, 'cssLength', $ok, 'D'));
        }
        foreach (['1px;color:red', 'calc(1px)', '-5px', ''] as $ko) {
            $this->assertSame('D', self::invoke(Shortcode::class, 'cssLength', $ko, 'D'), $ko);
        }
    }

    public function testUrlPublique(): void
    {
        $this->assertSame(
            'https://example.test/wp-content/uploads/docs/sous/b%20%C3%A9.txt',
            Shortcode::publicUrl('sous/b é.txt', realpath($this->uploads . '/docs'))
        );
        $this->assertSame('', Shortcode::publicUrl('s.txt', $this->tmp . '/secret'), 'hors uploads : pas d\'URL directe');
        $this->assertSame('', Shortcode::publicUrl('', $this->uploads));
    }
}
