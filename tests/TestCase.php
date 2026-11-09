<?php
namespace WPFN\Tests;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Arborescence temporaire propre à chaque test :
 *   <tmp>/uploads/            base autorisée (wp_upload_dir)
 *   <tmp>/uploads/docs/a.txt
 *   <tmp>/uploads/docs/sous/b.txt
 *   <tmp>/secret/s.txt        hors de la base
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected string $tmp;
    protected string $uploads;

    protected function setUp(): void
    {
        $this->tmp = realpath(sys_get_temp_dir()) . '/wpfn-test-' . bin2hex(random_bytes(6));
        $this->uploads = $this->tmp . '/uploads';
        $this->write('uploads/docs/a.txt', 'a');
        $this->write('uploads/docs/sous/b.txt', 'b');
        $this->write('secret/s.txt', 's');
        $GLOBALS['wpfn_test']['upload_dir'] = ['basedir' => $this->uploads, 'baseurl' => 'https://example.test/wp-content/uploads'];
        $GLOBALS['wpfn_test']['filters'] = [];
        $GLOBALS['wpfn_test']['logged_in'] = false;
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tmp);
    }

    protected function write(string $relative, string $content): string
    {
        $path = $this->tmp . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
        return $path;
    }

    /** Appelle une méthode privée statique ou d'instance. */
    protected static function invoke(object|string $target, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($target, $method);
        return $ref->invoke(is_object($target) ? $target : null, ...$args);
    }
}
