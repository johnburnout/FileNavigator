<?php
namespace WPFN\FileNavigator;

/**
 * Contrôle des fichiers envoyés : nom/extension (liste blanche + liste noire)
 * puis contenu réel (signature du fichier comparée à son extension).
 */
class UploadValidator
{
    /**
     * Extensions toujours refusées, y compris en « double extension » (ex. image.php.jpg) :
     * scripts exécutables côté serveur et fichiers de configuration du serveur web.
     */
    public const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx', 'jsp', 'shtml', 'shtm',
        'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'scr',
        'htaccess', 'htpasswd', 'ini', 'svg', 'svgz', 'js', 'mjs', 'htm', 'html', 'xhtml',
    ];

    /** @var array<string, string> motif d'extensions (ex. "jpg|jpeg|jpe") => type MIME */
    private array $mimes;

    public function __construct(?array $mimes = null)
    {
        $this->mimes = $mimes ?? self::defaultMimes();
    }

    /**
     * Types autorisés : ceux de WordPress (get_allowed_mime_types) + WebP,
     * personnalisables via le filtre « WPFN_allowed_mimes ».
     */
    public static function defaultMimes(): array
    {
        $mimes = function_exists('get_allowed_mime_types') ? get_allowed_mime_types() : [];
        $hasWebp = false;
        foreach (array_keys($mimes) as $pattern) {
            if (in_array('webp', explode('|', strtolower($pattern)), true)) {
                $hasWebp = true;
                break;
            }
        }
        if (!$hasWebp) {
            $mimes['webp'] = 'image/webp';
        }
        return function_exists('apply_filters') ? (array) apply_filters('WPFN_allowed_mimes', $mimes) : $mimes;
    }

    public function getMimes(): array
    {
        return $this->mimes;
    }

    /** @return string[] extensions autorisées (minuscules, triées) */
    public function allowedExtensions(): array
    {
        $exts = [];
        foreach (array_keys($this->mimes) as $pattern) {
            foreach (explode('|', strtolower($pattern)) as $ext) {
                if ($ext !== '' && !in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                    $exts[] = $ext;
                }
            }
        }
        $exts = array_values(array_unique($exts));
        sort($exts);
        return $exts;
    }

    /**
     * Vérifie le nom d'un fichier (extension autorisée, aucune extension dangereuse).
     * @throws \RuntimeException
     */
    public function checkName(string $name): void
    {
        $parts = explode('.', strtolower($name));
        if (count($parts) < 2 || end($parts) === '') {
            throw new \RuntimeException(sprintf('« %s » : les fichiers sans extension ne sont pas autorisés.', $name));
        }
        // Toutes les extensions intermédiaires (« .php.jpg ») et le nom lui-même s'il commence par un point
        foreach (array_slice($parts, 1) as $ext) {
            if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                throw new \RuntimeException(sprintf('« %s » : type de fichier interdit (.%s).', $name, $ext));
            }
        }
        $ext = end($parts);
        if (!in_array($ext, $this->allowedExtensions(), true)) {
            throw new \RuntimeException(sprintf('« %s » : extension .%s non autorisée.', $name, $ext));
        }
    }

    /**
     * Vérifie que le contenu réel du fichier correspond à son extension.
     * @throws \RuntimeException
     */
    public function checkContent(string $tmpPath, string $name): void
    {
        $this->checkName($name);

        if (function_exists('wp_check_filetype_and_ext')) {
            // Images : signature réelle (wp_get_image_mime) ; autres : finfo — refus si incohérent
            $check = wp_check_filetype_and_ext($tmpPath, $name, $this->mimes);
            if (empty($check['ext']) || empty($check['type'])) {
                throw new \RuntimeException(sprintf('« %s » : le contenu ne correspond pas à un type de fichier autorisé.', $name));
            }
            if (!empty($check['proper_filename']) && $check['proper_filename'] !== $name) {
                throw new \RuntimeException(sprintf('« %s » : le contenu ne correspond pas à l\'extension (fichier .%s en réalité).',
                    $name, pathinfo($check['proper_filename'], PATHINFO_EXTENSION)));
            }
        } elseif (function_exists('finfo_open')) {
            // Hors WordPress (tests) : contrôle minimal des images
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $real = (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmpPath);
            $expected = null;
            foreach ($this->mimes as $pattern => $mime) {
                if (in_array($ext, explode('|', strtolower($pattern)), true)) {
                    $expected = $mime;
                    break;
                }
            }
            if ($expected && strpos($expected, 'image/') === 0 && $real !== $expected) {
                throw new \RuntimeException(sprintf('« %s » : le contenu ne correspond pas à l\'extension.', $name));
            }
        }

        // Code PHP caché dans un fichier image (fichier « polyglotte »)
        if (preg_match('/\.(jpe?g|jpe|png|gif|webp|bmp|ico|tiff?|heic|avif)$/i', $name)) {
            $head = (string) file_get_contents($tmpPath, false, null, 0, 1024 * 1024);
            if (stripos($head, '<?php') !== false) {
                throw new \RuntimeException(sprintf('« %s » : contenu suspect détecté dans l\'image.', $name));
            }
        }
    }
}
