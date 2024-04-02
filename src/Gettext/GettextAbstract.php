<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\GettextCli;

/**
 * Abstract class for gettext operations.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class GettextAbstract
{
    /**
     * The regular expression to match the gettext tag: {domain}:{keyword}#{hash}
     * 
     * @var string
     */
    protected const TAG_RE = '/^(?:(?<domain>\w+):)?(?<keyword>\w+|\.)(?:#(?<hash>\w+))?$/';

    /**
     * The list of files to exclude from the gettext operations.
     * 
     * @var array<string>
     */
    protected const EXCLUDE_FILES = ['*/vendor/*', '*/tmp/*', '*/.*'];

    /**
     * The name of the HTML attribute to translate.
     * 
     * @var string
     */
    protected const MARKUP_NAME = 'gettext';

    /**
     * The source directory where the source files are located.
     *
     * @var string
     */
    public readonly string $modulePath;

    /**
     * The module name
     * 
     * @var string $moduleName 
     */
    public readonly string $moduleName;

    /**
     * The location of POT file.
     *
     * @var string
     */
    public readonly string $potFile;

    /**
     * The locales to process. Combination of locales from LINGUA file and supported locales.
     *
     * @var array<string> of locales in format 'en_US', 'cs_CZ', ...
     */
    public readonly array $locales;

    /**
     * Gettext domain
     * 
     * @var string
     */
    public readonly string $domain;

    public function __construct(string $modulePath)
    {
        global $api;

        $this->modulePath = $modulePath;
        $this->moduleName = (string) parse_url($api->fs->toZolingaUri($modulePath), PHP_URL_HOST) 
            or  throw new \RuntimeException("Cannot parse module name from $modulePath");
        $this->domain = basename($modulePath);
        $this->checkRequirements();

        $this->potFile = $this->modulePath . "/locale/messages.pot";

        if (!is_file($this->potFile)) {
            file_put_contents($this->potFile, "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Language: en\\n\"\n\n")
                or die("Cannot write to file: " . $this->potFile);
        }

        if (!is_file($this->modulePath . "/locale/LINGUAS")) {
            file_put_contents($this->modulePath . "/locale/LINGUAS", implode("\n", $api->locale->supportedLocales) . "\n")
                or die("Cannot create file: " . $this->modulePath . "/locale/LINGUAS");
        }

        $this->locales = array_unique([
            ...$api->locale->supportedLocales,
            ...array_filter(array_map('trim', explode("\n", trim(file_get_contents($this->modulePath . "/locale/LINGUAS") ?: '')))),
            "en_US"
        ]);
    }

    /**
     * Check the requirements for the gettext operations.
     *
     * @throw \Exception if the source directory does not exist or is not writable.
     * @throw \RuntimeException if the gettext extension is not loaded or the required commands are not executable.
     * @return void
     */
    private function checkRequirements()
    {
        global $api;

        if (!is_dir($this->modulePath)) {
            throw new \Exception("The gettext directory does not exist: " . $this->modulePath);
        }

        $path = $this->modulePath . "/locale";
        if (!is_dir($path)) {
            throw new \Exception("The gettext directory does not exist: " . $path);
        }
        if (!is_writable($path)) {
            throw new \Exception("The gettext directory is not writable: " . $path);
        }
        if (!is_readable($path)) {
            throw new \Exception("The source directory is not readable: " . $path);
        }
        foreach (['msginit', 'msgmerge', 'msgfmt'] as $cmd) {
            $cmdReal = trim((string) shell_exec("which $cmd")) 
                or throw new \RuntimeException("The command $cmd is not found.");
            if (!is_executable($cmdReal)) {
                throw new \RuntimeException("The command $cmd is not executable.");
            }
        }
        if (!extension_loaded('gettext')) {
            throw new \RuntimeException("The gettext extension is not loaded.");
        }
    }

    /**
     * All patterns are matched against the relative path of the file inside the source directory.
     * The matched path starts with "./" . The patterns are matched using the fnmatch function.
     *
     * @param array<string> $include The list of patterns to include.
     * @param array<string> $exclude The list of patterns to exclude.
     * @return array<string> The list of files matching the include and exclude patterns.
     */
    protected function findFiles(array $include, array $exclude = self::EXCLUDE_FILES): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->modulePath, \FilesystemIterator::CURRENT_AS_SELF)
        );

        foreach ($iterator as $file) {
            $path = "./" . $file->getSubPathname();
            if ($this->fnMatch($include, $path) && !$this->fnMatch($exclude, $path)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Match the path against the patterns.
     *
     * @param array<string> $patterns
     * @param string $path
     * @return boolean
     */
    private function fnMatch(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    protected function exec(string $cmd, string $message): string|null|false
    {
        $cwd = getcwd() or throw new \RuntimeException("Cannot get current working directory");
        chdir($this->modulePath);

        // echo " * Running: \033[0;32m " . str_replace("\033", "\\033", $cmd) . "\033[0m\n";

        // Redirect stderr on stdout and indent it:
        // Exec $cmd command and capture stderr and stdout
        $output = shell_exec($cmd);

        if (is_string($output) || is_null($output)) {
            GettextCli::log("⚡ $message");
        } else {
            GettextCli::log("🔴 ERROR: $cmd output is " . json_encode($output));
        }

        chdir($cwd);

        return $output;
    }

    protected function loadHtmlFile(string $file): \DOMDocument
    {
        $doc = new \DOMDocument();
        // fix html5/svg errors - libxml_use_internal_errors
        $errorsVal = libxml_use_internal_errors();
        libxml_use_internal_errors(true);

        $content = file_get_contents($file) or throw new \RuntimeException("Cannot read file: $file");

        // If it contains <meta ... content="text/html; charset=UTF-8"> or <meta ... charset="UTF-8">, we don't need to add it 
        if (!preg_match('/<meta[^>]+charset=/i', $content)) {
            if (stripos($content, '</head')) {
                $content = str_replace('</head', '<meta charset="UTF-8" /></head', $content);
            }
        }

        $doc->loadHTML($content, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_NOXMLDECL);
        libxml_use_internal_errors($errorsVal);

        return $doc;
    }

    /**
     * Find all translatable elements in the HTML document.
     *
     * @param \DOMDocument $doc
     * @param bool $addUtfMeta Add <meta charset="UTF-8"> if not present.
     * @return array<\DOMAttr>
     */
    protected function findTranslatables(\DOMDocument $doc, bool $addUtfMeta = false): array
    {
        $xpath = new \DOMXPath($doc);

        if ($addUtfMeta) {
            $head = $doc->getElementsByTagName('head')->item(0);
            if ($head && !$xpath->evaluate('count(//meta[@charset="UTF-8"])', $head)) {
                $meta = $doc->createElement('meta');
                $meta->setAttribute('charset', 'UTF-8');
                $head->appendChild($meta);
            }
        }

        $results = $xpath->query("//@" . self::MARKUP_NAME)
            or throw new \RuntimeException("Cannot query the document to extract translatable elements: " . self::MARKUP_NAME);
        /** @var array<\DOMAttr> $ret */
        $ret = iterator_to_array($results);
        return $ret;
    }

    /**
     * Parse @gettext attribute and return array of [domain, keyword, hash].
     *
     * @param string $tags of white space seaparated tags in format [domain:](attribute|.)[#hash]
     * @return array<string, array{domain: ?string, keyword: string, hash: ?string}> of tag => [domain, keyword, hash]
     */
    protected function parseGettextAttr(string $tags): array
    {
        $list = [];

        foreach (preg_split('/\s+/', $tags) ?: [] as $tag) {
            if (!preg_match(self::TAG_RE, $tag, $matches)) {
                GettextCli::log("🔴 ERROR: Invalid gettext tag: $tag");
                continue;
            }
            $list[$tag] = [
                'domain' => $matches['domain'] ?? null,
                'keyword' => $matches['keyword'],
                'hash' => $matches['hash'] ?? null
            ];
        }

        return $list;
    }

    /**
     * Extract the value from <meta name="gettext" content="{VALUE}">.
     *
     * @param string $file
     * @param string|null $default
     * @return string|null
     */
    protected function getGettextMode(string $file, ?string $default = null): string|null
    {
        $meta = get_meta_tags($file);
        return $meta['gettext'] ?? $default;
    }
    
    /**
     * Calculate the short hash from the string.
     *
     * @param string $strid
     * @return string
     */
    protected function calculateHash(string $strid): string
    {
        return substr(sha1($strid), 0, 6);
    }
}
