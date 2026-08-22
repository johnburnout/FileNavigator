<?php
namespace WPFB\FileBrowser;

use Symfony\Component\Filesystem\Path;

class SecurityChecker
{
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = realpath($rootPath) ?: $rootPath;
    }

    public function isPathSafe(string $absolutePath): bool
    {
        $realRoot = realpath($this->rootPath);
        $realTarget = realpath($absolutePath);
        
        if ($realRoot === false || $realTarget === false) {
            return false;
        }
        
        // Normaliser les chemins (supprimer les slashes finaux)
        $root = rtrim($realRoot, '/') . '/';
        $target = rtrim($realTarget, '/') . '/';
        
        // Vérifier que le chemin cible commence par le chemin racine
        return strpos($target, $root) === 0;
    }
}