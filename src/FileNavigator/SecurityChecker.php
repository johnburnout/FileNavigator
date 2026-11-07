<?php
namespace WPFN\FileNavigator;

use Symfony\Component\Filesystem\Path;

class SecurityChecker
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = realpath($rootPath) ?: $rootPath;
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /**
     * Chemin absolu d'un chemin relatif à la racine (non vérifié : utiliser isPathSafe()).
     */
    public function resolve(string $relative): string
    {
        $safe = Path::canonicalize($relative);
        if ($safe === '' || $safe === '.') {
            return $this->rootPath;
        }
        return Path::makeAbsolute($safe, $this->rootPath);
    }

    /**
     * Le chemin existe-t-il et se trouve-t-il (liens résolus) sous la racine ?
     */
    public function isPathSafe(string $absolutePath): bool
    {
        $realRoot = realpath($this->rootPath);
        $realTarget = realpath($absolutePath);

        if ($realRoot === false || $realTarget === false) {
            return false;
        }

        $root = rtrim($realRoot, '/') . '/';
        $target = rtrim($realTarget, '/') . '/';
        return str_starts_with($target, $root);
    }
}
