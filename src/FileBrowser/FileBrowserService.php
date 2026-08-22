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

    /**
     * Retourne la liste des fichiers/dossiers dans le répertoire $dir (chemin relatif ou absolu).
     */
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
            // Filtrage par extension (pour les fichiers)
            if (!empty($options['extensions']) && $fileInfo->isFile()) {
                $ext = pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), array_map('strtolower', $options['extensions']))) {
                    continue;
                }
            }
            $items[] = [
                'name' => $fileInfo->getFilename(),
                'path' => $fileInfo->getPathname(),
                'relative' => Path::makeRelative($fileInfo->getPathname(), $this->rootPath),
                'isDir' => $fileInfo->isDir(),
                'size' => $fileInfo->isFile() ? $fileInfo->getSize() : 0,
                'mtime' => $fileInfo->getMTime(),
                'isHidden' => $fileInfo->getFilename()[0] === '.',
            ];
        }
        
        return $items;
    }

    private function buildAbsolutePath(string $relative): string
    {
        // Nettoyer les '..' et autres
        $safe = Path::canonicalize($relative);
        // Si le chemin est vide ou '.', on prend la racine
        if ($safe === '' || $safe === '.') {
            return $this->rootPath;
        }
        // Sinon, on construit absolu
        return Path::makeAbsolute($safe, $this->rootPath);
    }
}