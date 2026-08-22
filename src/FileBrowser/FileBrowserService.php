<?php
namespace WPFB\FileBrowser;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;

class FileBrowserService
{
    private string $rootPath;
    private SecurityChecker $security;

    public function __construct(string $rootPath)
    {
        $this->rootPath = realpath($rootPath) ?: $rootPath;
        $this->security = new SecurityChecker($this->rootPath);
    }

    public function listDirectory(string $dir = '', array $options = []): array
    {
        $defaults = [
            'extensions' => [],
            'sort' => 'name',
            'order' => 'asc',
            'show_hidden' => false,
        ];
        $options = array_merge($defaults, $options);

        $currentDir = $this->buildAbsolutePath($dir);
        if (!$this->security->isPathSafe($currentDir)) {
            throw new \RuntimeException('Chemin non autorisé');
        }
        if (!is_dir($currentDir)) {
            throw new \RuntimeException('Le répertoire n\'existe pas');
        }

        $finder = new Finder();
        $finder->in($currentDir)->depth(0);

        if (!$options['show_hidden']) {
            $finder->notName('/^\..*$/');
        }

        // Tri selon l'option
        switch ($options['sort']) {
            case 'size':
                $finder->sortBySize();
                break;
            case 'mtime':
                $finder->sortByModifiedTime();
                break;
            case 'name':
            default:
                $finder->sortByName();
                break;
        }

        if ($options['order'] === 'desc') {
            $finder->reverseSorting();
        }

        $items = [];
        foreach ($finder as $fileInfo) {
            // Filtrer par extension pour les fichiers
            if (!empty($options['extensions']) && $fileInfo->isFile()) {
                $ext = pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), array_map('strtolower', $options['extensions']))) {
                    continue;
                }
            }

            // 🔧 Correction : utiliser is_dir() sur le chemin réel pour une détection fiable
//          $realPath = $fileInfo->getPathname();
//          $isDir = is_dir($realPath);
//
//          // Calcul du chemin relatif (sécurisé)
//          $relative = Path::makeRelative($realPath, $this->rootPath);
//          // Si le chemin relatif commence par '..', on le recalcule manuellement
//          if (strpos($relative, '..') === 0) {
//              $relative = ltrim(str_replace($this->rootPath, '', $realPath), '/');
//              // Si le résultat est vide, on met le nom de base
//              if (empty($relative)) {
//                  $relative = $fileInfo->getFilename();
//              }
            
            $realPath = $fileInfo->getPathname();
            $isDir = is_dir($realPath);
            
            // Calcul du chemin relatif (robuste)
            $relative = Path::makeRelative($realPath, $this->rootPath);
            if (strpos($relative, '..') === 0 || $relative === '') {
                // Recalcul manuel
                $relative = ltrim(str_replace($this->rootPath, '', $realPath), '/');
                if (empty($relative)) {
                    $relative = $fileInfo->getFilename();
                }
            }

            $items[] = [
                'name' => $fileInfo->getFilename(),
                'path' => $realPath,
                'relative' => $relative,
                'isDir' => $isDir,
                'size' => $fileInfo->isFile() ? $fileInfo->getSize() : 0,
                'mtime' => $fileInfo->getMTime(),
                'isHidden' => $fileInfo->getFilename()[0] === '.',
            ];
        }

        return $items;
    }

    private function buildAbsolutePath(string $relative): string
    {
        $safe = Path::canonicalize($relative);
        if ($safe === '' || $safe === '.') {
            return $this->rootPath;
        }
        return Path::makeAbsolute($safe, $this->rootPath);
    }
}