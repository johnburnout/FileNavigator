<?php
namespace WPFN\FileNavigator;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;

class FileNavigatorService
{
    private string $rootPath;
    private SecurityChecker $security;

    public function __construct(string $rootPath)
    {
        $this->security = new SecurityChecker($rootPath);
        $this->rootPath = $this->security->getRootPath();
    }

    public function listDirectory(string $dir = '', array $options = []): array
    {
        $options = array_merge([
            'extensions' => [],
            'sort' => 'name',
            'order' => 'asc',
            'show_hidden' => false,
        ], $options);

        $currentDir = $this->security->resolve($dir);
        if (!$this->security->isPathSafe($currentDir)) {
            throw new \RuntimeException('Chemin non autorisé');
        }
        if (!is_dir($currentDir)) {
            throw new \RuntimeException('Le répertoire n\'existe pas');
        }

        $finder = (new Finder())->in($currentDir)->depth(0);
        if (!$options['show_hidden']) {
            $finder->notName('/^\..*$/');
        }

        match ($options['sort']) {
            'size' => $finder->sortBySize(),
            'mtime' => $finder->sortByModifiedTime(),
            default => $finder->sortByName(),
        };
        if ($options['order'] === 'desc') {
            $finder->reverseSorting();
        }

        $extensions = array_map('strtolower', $options['extensions']);

        $items = [];
        foreach ($finder as $fileInfo) {
            $name = $fileInfo->getFilename();
            // Filtre par extension (fichiers uniquement)
            if ($extensions && $fileInfo->isFile()
                && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $extensions, true)) {
                continue;
            }

            $realPath = $fileInfo->getPathname();
            $items[] = [
                'name' => $name,
                'path' => $realPath,
                'relative' => $this->relativePath($realPath, $name),
                'isDir' => is_dir($realPath),
                'size' => $fileInfo->isFile() ? $fileInfo->getSize() : 0,
                'mtime' => $fileInfo->getMTime(),
                'isHidden' => $name[0] === '.',
            ];
        }

        return $items;
    }

    private function relativePath(string $absolute, string $name): string
    {
        $relative = Path::makeRelative($absolute, $this->rootPath);
        if (!str_starts_with($relative, '..') && $relative !== '') {
            return $relative;
        }
        // Recalcul manuel (racine non canonique, liens…)
        $relative = ltrim(str_replace($this->rootPath, '', $absolute), '/');
        return $relative !== '' ? $relative : $name;
    }
}
