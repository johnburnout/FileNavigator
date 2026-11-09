<?php
namespace WPFN\FileNavigator;

use Symfony\Component\Filesystem\Filesystem;

class FileOperationService
{
    private SecurityChecker $security;
    private UploadValidator $validator;
    private Filesystem $fs;

    public function __construct(string $rootPath, ?UploadValidator $validator = null)
    {
        $this->security = new SecurityChecker($rootPath);
        $this->validator = $validator ?? new UploadValidator();
        $this->fs = new Filesystem();
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
        $absoluteTarget = $this->resolveDirectory($targetDir);
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
        $absoluteTarget = $this->resolveDirectory($targetDir);
        $existing = [];
        foreach (array_unique(array_map('strval', $names)) as $name) {
            $name = $this->validateName($name);
            $path = $absoluteTarget . '/' . $name;
            if ($this->exists($path)) {
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
        $absoluteTarget = $this->resolveDirectory($targetDir);

        // Noms réservés : on évite qu'un « garder les deux » prenne le nom d'un autre élément envoyé
        $reserved = [];
        foreach ($items as $item) {
            $reserved[] = $this->validateName((string) ($item['name'] ?? ''));
        }

        $mapping = [];
        foreach ($items as $item) {
            $name = $this->validateName((string) ($item['name'] ?? ''));
            $isDir = !empty($item['isDir']);
            $action = (string) ($item['action'] ?? 'new');
            $path = $absoluteTarget . '/' . $name;
            if (!$isDir) {
                $this->validator->checkName($name);
            }
            if (!$this->exists($path)) {
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
                    $this->fs->remove($path);
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
        if (!$this->exists($dir . '/' . $name)) {
            return $name;
        }
        $taken = fn (string $candidate): bool =>
            $this->exists($dir . '/' . $candidate) || in_array($candidate, $reserved, true);

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
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'fichier trop volumineux',
            UPLOAD_ERR_PARTIAL => 'envoi incomplet',
            UPLOAD_ERR_NO_FILE => 'aucun fichier reçu',
            UPLOAD_ERR_NO_TMP_DIR => 'dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'écriture impossible sur le disque',
            default => 'erreur ' . $code,
        };
    }

    public function delete(array $paths): void
    {
        foreach ($paths as $relative) {
            $this->fs->remove($this->resolveItem($relative));
        }
    }

    public function rename(string $oldRelative, string $newName): void
    {
        $oldAbsolute = $this->resolveItem($oldRelative);
        $newName = $this->validateName($newName);
        if (is_file($oldAbsolute)) {
            $this->validator->checkName($newName); // empêche par ex. photo.jpg -> photo.php
        }
        $dir = dirname($oldAbsolute);
        $newAbsolute = $dir . '/' . $newName;
        if (file_exists($newAbsolute)) {
            throw new \RuntimeException('Un fichier/dossier existe déjà avec ce nom.');
        }
        $this->fs->rename($oldAbsolute, $newAbsolute);
    }

    public function paste(array $sourcePaths, string $targetDir, string $mode): void
    {
        $absoluteTarget = $this->security->resolve($targetDir);
        if (!$this->security->isPathSafe($absoluteTarget)) {
            throw new \RuntimeException('Chemin cible non autorisé');
        }
        foreach ($sourcePaths as $relative) {
            $sourceAbsolute = $this->resolveItem($relative);
            $isDir = is_dir($sourceAbsolute);
            // Un dossier ne peut pas être collé dans lui-même ni dans l'un de ses sous-dossiers
            // (déplacement impossible, copie récursive sans fin)
            $realSource = realpath($sourceAbsolute);
            $realTarget = realpath($absoluteTarget);
            if ($isDir && ($realTarget === $realSource || str_starts_with($realTarget . '/', $realSource . '/'))) {
                throw new \RuntimeException(sprintf('Impossible de coller le dossier « %s » dans lui-même ou dans l\'un de ses sous-dossiers.', basename($relative)));
            }
            $dest = $absoluteTarget . '/' . $this->uniqueName($absoluteTarget, basename($relative), $isDir);
            if ($mode !== 'copy') { // cut
                $this->fs->rename($sourceAbsolute, $dest);
            } elseif ($isDir) {
                $this->fs->mirror($sourceAbsolute, $dest);
            } else {
                $this->fs->copy($sourceAbsolute, $dest);
            }
        }
    }

    public function download(array $paths): void
    {
        if (count($paths) === 1) {
            $absolute = $this->security->resolve($paths[0]);
            if (!$this->security->isPathSafe($absolute)) {
                throw new \RuntimeException('Chemin non autorisé');
            }
            if (is_file($absolute)) {
                $name = basename($paths[0]);
                // Repli ASCII sans guillemets/contrôles + forme RFC 5987 pour les noms UTF-8
                $fallback = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $name);
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
                readfile($absolute);
                exit;
            }
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Le téléchargement groupé nécessite l\'extension PHP « zip », absente sur ce serveur.');
        }
        // Inventaire préalable : chemins vérifiés, noms d'entrées dérivés du chemin réel
        // (jamais de « ../ » dans l'archive) et limites de taille pour éviter les abus
        $limits = (array) apply_filters('WPFN_zip_limits', ['max_size' => 512 * MB_IN_BYTES, 'max_files' => 2000], is_user_logged_in());
        $limits = ['max_size' => (int) ($limits['max_size'] ?? 0), 'max_files' => (int) ($limits['max_files'] ?? 0)];
        $root = rtrim($this->security->getRootPath(), '/') . '/';
        $entries = [];
        $totalSize = 0;
        foreach ($paths as $relative) {
            $absolute = $this->security->resolve($relative);
            if (!$this->security->isPathSafe($absolute)) {
                throw new \RuntimeException("Chemin non autorisé: $relative");
            }
            $files = is_dir($absolute)
                ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absolute, \RecursiveDirectoryIterator::SKIP_DOTS))
                : [new \SplFileInfo($absolute)];
            foreach ($files as $file) {
                $real = $file->getRealPath();
                if ($real === false || !$file->isFile() || !str_starts_with($real, $root)) {
                    continue; // lien symbolique sortant de la racine, fichier spécial…
                }
                $entries[substr($real, strlen($root))] = $real;
                $totalSize += $file->getSize();
                if (count($entries) > $limits['max_files'] || $totalSize > $limits['max_size']) {
                    throw new \RuntimeException(sprintf(
                        'Sélection trop volumineuse pour un téléchargement groupé (maximum %d fichiers, %s).',
                        $limits['max_files'],
                        size_format($limits['max_size'])
                    ));
                }
            }
        }
        if ($entries === []) {
            throw new \RuntimeException('Aucun fichier à télécharger.');
        }

        $zip = new \ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'WPFN_');
        if ($zip->open($tempFile, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer le ZIP.');
        }
        foreach ($entries as $name => $real) {
            $zip->addFile($real, $name);
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="download.zip"');
        readfile($tempFile);
        unlink($tempFile);
        exit;
    }

    /**
     * Chemin absolu d'un élément existant sous la racine, à l'exclusion de la racine elle-même
     * (qu'on ne doit ni supprimer, ni renommer, ni déplacer).
     */
    private function resolveItem(string $relative): string
    {
        $absolute = $this->security->resolve($relative);
        if (!$this->security->isPathSafe($absolute)) {
            throw new \RuntimeException("Chemin non autorisé: $relative");
        }
        if (realpath($absolute) === realpath($this->security->getRootPath())) {
            throw new \RuntimeException('Le dossier racine ne peut pas être modifié.');
        }
        return $absolute;
    }

    /**
     * Chemin absolu d'un dossier existant sous la racine.
     */
    private function resolveDirectory(string $relative): string
    {
        $absolute = $this->security->resolve($relative);
        if (!is_dir($absolute) || !$this->security->isPathSafe($absolute)) {
            throw new \RuntimeException('Chemin non autorisé');
        }
        return $absolute;
    }

    /** Existe (y compris lien symbolique cassé). */
    private function exists(string $path): bool
    {
        return file_exists($path) || is_link($path);
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
        
        $absoluteParent = $this->security->resolve($parentDir);
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
        
        $this->fs->mkdir($newPath);
    }
}