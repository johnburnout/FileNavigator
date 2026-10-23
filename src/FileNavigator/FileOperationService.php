<?php
namespace WPFN\FileNavigator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class FileOperationService
{
    private string $rootPath;
    private SecurityChecker $security;
    private UploadValidator $validator;

    public function __construct(string $rootPath, ?UploadValidator $validator = null)
    {
        $this->rootPath = realpath($rootPath) ?: $rootPath;
        $this->security = new SecurityChecker($this->rootPath);
        $this->validator = $validator ?? new UploadValidator();
    }

    /**
     * Upload de fichiers.
     * - Sans $relativePaths : comportement historique (nom de base, suffixe _N si le nom existe).
     * - Avec $relativePaths : chemins relatifs (dossiers glissés-déposés), les sous-dossiers sont
     *   créés et les fichiers existants écrasés (les conflits ont été résolus par prepareUpload()).
     * Chaque fichier est contrôlé (extension + contenu) ; un fichier refusé n'interrompt pas l'envoi.
     * @return array{files: string[], rejected: array<int, array{path: string, reason: string}>}
     */
    public function upload(array $files, string $targetDir, ?array $relativePaths = null, array $emptyDirs = []): array
    {
        $absoluteTarget = $this->buildAbsolutePath($targetDir);
        if (!is_dir($absoluteTarget) || !$this->security->isPathSafe($absoluteTarget)) {
            throw new \RuntimeException('Chemin non autorisé');
        }
        $uploaded = [];
        $rejected = [];

        foreach ($emptyDirs as $dir) {
            $this->ensureDirectory($absoluteTarget, $this->validateRelativePath((string) $dir));
        }

        $tmpNames = isset($files['tmp_name']) ? (array) $files['tmp_name'] : [];
        foreach ($tmpNames as $index => $tmp) {
            $origName = (string) ($files['name'][$index] ?? '');
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException(sprintf('Échec de l\'envoi de « %s » (%s).', $origName, $this->uploadErrorMessage($error)));
            }
            if (!is_uploaded_file($tmp)) {
                throw new \RuntimeException(sprintf('Fichier « %s » invalide.', $origName));
            }

            $displayPath = $relativePaths[$index] ?? $origName;
            try {
                $segments = explode('/', $relativePaths !== null ? (string) $displayPath : $origName);
                $checkName = (string) end($segments);
                $this->validator->checkContent($tmp, $checkName);
            } catch (\RuntimeException $e) {
                $rejected[] = ['path' => (string) $displayPath, 'reason' => $e->getMessage()];
                @unlink($tmp);
                continue;
            }

            if ($relativePaths !== null) {
                if (!isset($relativePaths[$index])) {
                    throw new \RuntimeException(sprintf('Chemin manquant pour « %s ».', $origName));
                }
                $relative = $this->validateRelativePath((string) $relativePaths[$index]);
                $segments = explode('/', $relative);
                $fileName = array_pop($segments);
                $parent = $segments ? $this->ensureDirectory($absoluteTarget, implode('/', $segments)) : $absoluteTarget;
                $dest = $parent . '/' . $fileName;
                if (is_dir($dest)) {
                    throw new \RuntimeException(sprintf('Un dossier « %s » existe déjà à la place de ce fichier.', $relative));
                }
            } else {
                $name = $this->validateName(basename($origName));
                $relative = $this->uniqueName($absoluteTarget, $name, false);
                $dest = $absoluteTarget . '/' . $relative;
            }

            if (!move_uploaded_file($tmp, $dest)) {
                throw new \RuntimeException(sprintf('Impossible d\'enregistrer « %s ».', $relative));
            }
            $uploaded[] = $relative;
        }
        return ['files' => $uploaded, 'rejected' => $rejected];
    }

    /**
     * Retourne les éléments de premier niveau qui existent déjà dans le dossier cible.
     * @return array<int, array{name: string, isDir: bool}>
     */
    public function findExisting(array $names, string $targetDir): array
    {
        $absoluteTarget = $this->buildAbsolutePath($targetDir);
        if (!is_dir($absoluteTarget) || !$this->security->isPathSafe($absoluteTarget)) {
            throw new \RuntimeException('Chemin non autorisé');
        }
        $existing = [];
        foreach (array_unique(array_map('strval', $names)) as $name) {
            $name = $this->validateName($name);
            $path = $absoluteTarget . '/' . $name;
            if (file_exists($path) || is_link($path)) {
                $existing[] = ['name' => $name, 'isDir' => is_dir($path)];
            }
        }
        return $existing;
    }

    /**
     * Applique les choix de l'utilisateur pour chaque élément de premier niveau avant l'envoi.
     * $items : liste de ['name' => string, 'isDir' => bool, 'action' => 'new'|'replace'|'merge'|'keep']
     *   - replace : l'élément existant est supprimé ;
     *   - merge   : (dossiers uniquement) le contenu est ajouté au dossier existant ;
     *   - keep    : un nouveau nom unique est attribué (nom_1, nom_2…).
     * @return array<string, string> nom d'origine => nom final
     */
    public function prepareUpload(array $items, string $targetDir): array
    {
        $absoluteTarget = $this->buildAbsolutePath($targetDir);
        if (!is_dir($absoluteTarget) || !$this->security->isPathSafe($absoluteTarget)) {
            throw new \RuntimeException('Chemin non autorisé');
        }

        // Noms réservés : on évite qu'un « garder les deux » prenne le nom d'un autre élément envoyé
        $reserved = [];
        foreach ($items as $item) {
            $reserved[] = $this->validateName((string) ($item['name'] ?? ''));
        }

        $fs = new Filesystem();
        $mapping = [];
        foreach ($items as $item) {
            $name = $this->validateName((string) ($item['name'] ?? ''));
            $isDir = !empty($item['isDir']);
            $action = (string) ($item['action'] ?? 'new');
            $path = $absoluteTarget . '/' . $name;
            $exists = file_exists($path) || is_link($path);

            if (!$isDir) {
                $this->validator->checkName($name);
            }
            if (!$exists) {
                $mapping[$name] = $name;
                continue;
            }

            switch ($action) {
                case 'replace':
                    if (!is_link($path) && !$this->security->isPathSafe($path)) {
                        throw new \RuntimeException(sprintf('Chemin non autorisé : %s', $name));
                    }
                    // Fichier remplacé par un fichier : écrasé à l'envoi (l'ancien est conservé si le nouveau est refusé)
                    if (!$isDir && is_file($path) && !is_link($path)) {
                        $mapping[$name] = $name;
                        break;
                    }
                    $fs->remove($path);
                    $mapping[$name] = $name;
                    break;

                case 'merge':
                    if (!$isDir || !is_dir($path)) {
                        throw new \RuntimeException(sprintf('Impossible de fusionner « %s » : seuls deux dossiers peuvent être fusionnés.', $name));
                    }
                    if (!$this->security->isPathSafe($path)) {
                        throw new \RuntimeException(sprintf('Chemin non autorisé : %s', $name));
                    }
                    $mapping[$name] = $name;
                    break;

                case 'keep':
                default:
                    $newName = $this->uniqueName($absoluteTarget, $name, $isDir, $reserved);
                    $reserved[] = $newName;
                    $mapping[$name] = $newName;
                    break;
            }
        }
        return $mapping;
    }

    /**
     * Génère un nom libre dans $dir : nom_1.ext, nom_2.ext… (nom_1 pour un dossier).
     */
    private function uniqueName(string $dir, string $name, bool $isDir, array $reserved = []): string
    {
        $taken = function (string $candidate) use ($dir, $reserved): bool {
            $path = $dir . '/' . $candidate;
            return file_exists($path) || is_link($path) || in_array($candidate, $reserved, true);
        };
        if (!file_exists($dir . '/' . $name) && !is_link($dir . '/' . $name)) {
            return $name;
        }

        $base = $name;
        $ext = '';
        if (!$isDir) {
            $dot = strrpos($name, '.');
            if ($dot !== false && $dot > 0) {
                $base = substr($name, 0, $dot);
                $ext = substr($name, $dot);
            }
        }
        $counter = 1;
        do {
            $candidate = $base . '_' . $counter . $ext;
            $counter++;
        } while ($taken($candidate));
        return $candidate;
    }

    /**
     * Crée (si besoin) l'arborescence $relative sous $base, en vérifiant chaque niveau.
     */
    private function ensureDirectory(string $base, string $relative): string
    {
        $current = $base;
        foreach (explode('/', $relative) as $segment) {
            $current .= '/' . $segment;
            if (file_exists($current) && !is_dir($current)) {
                throw new \RuntimeException(sprintf('Un fichier « %s » existe déjà à la place d\'un dossier.', $segment));
            }
            if (!is_dir($current) && !@mkdir($current, 0755) && !is_dir($current)) {
                throw new \RuntimeException(sprintf('Impossible de créer le dossier « %s ».', $segment));
            }
            if (!$this->security->isPathSafe($current)) {
                throw new \RuntimeException('Chemin non autorisé');
            }
        }
        return $current;
    }

    /**
     * Valide un chemin relatif « a/b/c.txt » (aucun segment vide, « . » ou « .. »).
     */
    private function validateRelativePath(string $relative): string
    {
        $relative = trim($relative, '/');
        if ($relative === '') {
            throw new \RuntimeException('Chemin vide.');
        }
        $segments = array_map([$this, 'validateName'], explode('/', $relative));
        return implode('/', $segments);
    }

    /**
     * Valide un nom de fichier ou de dossier (un seul segment).
     */
    private function validateName(string $name): string
    {
        if ($name === '' || $name === '.' || $name === '..'
            || strlen($name) > 255
            || preg_match('#[/\\\\\x00-\x1F\x7F]#', $name)) {
            throw new \RuntimeException(sprintf('Nom invalide : « %s ».', $name));
        }
        return $name;
    }

    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'fichier trop volumineux';
            case UPLOAD_ERR_PARTIAL:
                return 'envoi incomplet';
            case UPLOAD_ERR_NO_FILE:
                return 'aucun fichier reçu';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'dossier temporaire manquant';
            case UPLOAD_ERR_CANT_WRITE:
                return 'écriture impossible sur le disque';
            default:
                return 'erreur ' . $code;
        }
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
        $newName = $this->validateName($newName);
        if (is_file($oldAbsolute)) {
            $this->validator->checkName($newName); // empêche par ex. photo.jpg -> photo.php
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