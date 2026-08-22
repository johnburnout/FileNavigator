<?php
namespace WPFN\FileNavigator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class FileOperationService
{
    private string $rootPath;
    private SecurityChecker $security;

    public function __construct(string $rootPath)
    {
        $this->rootPath = realpath($rootPath) ?: $rootPath;
        $this->security = new SecurityChecker($this->rootPath);
    }

    public function upload(array $files, string $targetDir): array
    {
        $absoluteTarget = $this->buildAbsolutePath($targetDir);
        if (!$this->security->isPathSafe($absoluteTarget)) {
            throw new \RuntimeException('Chemin non autorisé');
        }
        $fs = new Filesystem();
        $uploaded = [];
        foreach ($files['tmp_name'] as $index => $tmp) {
            $name = basename($files['name'][$index]);
            $dest = $absoluteTarget . '/' . $name;
            if ($fs->exists($dest)) {
                // On peut ajouter un suffixe
                $info = pathinfo($name);
                $base = $info['filename'];
                $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
                $counter = 1;
                do {
                    $newName = $base . '_' . $counter . $ext;
                    $dest = $absoluteTarget . '/' . $newName;
                    $counter++;
                } while ($fs->exists($dest));
            }
            $fs->copy($tmp, $dest);
            $uploaded[] = basename($dest);
        }
        return $uploaded;
    }

    public function delete(array $paths): void
    {
        $fs = new Filesystem();
        foreach ($paths as $relative) {
            $absolute = $this->buildAbsolutePath($relative);
            if (!$this->security->isPathSafe($absolute)) {
                throw new \RuntimeException("Chemin non autorisé: $relative");
            }
            if ($fs->exists($absolute)) {
                $fs->remove($absolute);
            }
        }
    }

    public function rename(string $oldRelative, string $newName): void
    {
        $oldAbsolute = $this->buildAbsolutePath($oldRelative);
        if (!$this->security->isPathSafe($oldAbsolute)) {
            throw new \RuntimeException('Chemin non autorisé');
        }
        $dir = dirname($oldAbsolute);
        $newAbsolute = $dir . '/' . $newName;
        if (file_exists($newAbsolute)) {
            throw new \RuntimeException('Un fichier/dossier existe déjà avec ce nom.');
        }
        $fs = new Filesystem();
        $fs->rename($oldAbsolute, $newAbsolute);
    }

    public function paste(array $sourcePaths, string $sourceDir, string $targetDir, string $mode): void
    {
        $absoluteTarget = $this->buildAbsolutePath($targetDir);
        if (!$this->security->isPathSafe($absoluteTarget)) {
            throw new \RuntimeException('Chemin cible non autorisé');
        }
        $fs = new Filesystem();
        foreach ($sourcePaths as $relative) {
            $sourceAbsolute = $this->buildAbsolutePath($relative);
            if (!$this->security->isPathSafe($sourceAbsolute)) {
                throw new \RuntimeException("Chemin source non autorisé: $relative");
            }
            $name = basename($relative);
            $dest = $absoluteTarget . '/' . $name;
            if ($fs->exists($dest)) {
                $info = pathinfo($name);
                $base = $info['filename'];
                $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
                $counter = 1;
                do {
                    $newName = $base . '_' . $counter . $ext;
                    $dest = $absoluteTarget . '/' . $newName;
                    $counter++;
                } while ($fs->exists($dest));
            }
            if ($mode === 'copy') {
                if (is_dir($sourceAbsolute)) {
                    $fs->mirror($sourceAbsolute, $dest);
                } else {
                    $fs->copy($sourceAbsolute, $dest);
                }
            } else { // cut
                $fs->rename($sourceAbsolute, $dest);
            }
        }
    }

    public function download(array $paths): void
    {
        if (count($paths) === 1) {
            $absolute = $this->buildAbsolutePath($paths[0]);
            if (!$this->security->isPathSafe($absolute)) {
                throw new \RuntimeException('Chemin non autorisé');
            }
            if (is_file($absolute)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($paths[0]) . '"');
                readfile($absolute);
                exit;
            }
        }

        $zip = new \ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'WPFN_');
        if ($zip->open($tempFile, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Impossible de créer le ZIP.');
        }

        foreach ($paths as $relative) {
            $absolute = $this->buildAbsolutePath($relative);
            if (!$this->security->isPathSafe($absolute)) {
                throw new \RuntimeException("Chemin non autorisé: $relative");
            }
            if (is_file($absolute)) {
                $zip->addFile($absolute, $relative);
            } elseif (is_dir($absolute)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($absolute, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($files as $file) {
                    $zip->addFile($file->getPathname(), $relative . '/' . $file->getRelativePathname());
                }
            }
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="download.zip"');
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }

    private function buildAbsolutePath(string $relative): string
    {
        $safe = Path::canonicalize($relative);
        if ($safe === '' || $safe === '.') {
            return $this->rootPath;
        }
        return Path::makeAbsolute($safe, $this->rootPath);
    }
    
    public function createDirectory(string $newDirName, string $parentDir): void
    {
        // Normaliser le nom (supprimer les caractères dangereux)
        $newDirName = trim($newDirName);
        if (empty($newDirName)) {
            throw new \RuntimeException('Le nom du dossier ne peut pas être vide.');
        }
        // Interdire les séparateurs de chemin et les caractères spéciaux
        if (preg_match('#[\\/:"*?<>|]#', $newDirName)) {
            throw new \RuntimeException('Nom de dossier invalide (caractères interdits).');
        }
        
        $absoluteParent = $this->buildAbsolutePath($parentDir);
        if (!$this->security->isPathSafe($absoluteParent)) {
            throw new \RuntimeException('Chemin parent non autorisé');
        }
        if (!is_dir($absoluteParent)) {
            throw new \RuntimeException('Le dossier parent n\'existe pas.');
        }
        
        $newPath = $absoluteParent . '/' . $newDirName;
        if (file_exists($newPath)) {
            throw new \RuntimeException('Un fichier ou dossier existe déjà avec ce nom.');
        }
        
        $fs = new Filesystem();
        $fs->mkdir($newPath);
    }
}