<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\Types\FileTypes;
use const Zolinga\System\ROOT_DIR;

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
     * File extensions to ignore when scanning for files.
     * 
     * @var array<string>
     */
    protected const IGNORED_EXTENSIONS = ['*~', '*.bak', '*.orig', '*.swp'];

    /**
     * The name of the HTML attribute to translate.
     * 
     * @var string
     */
    protected const MARKUP_NAME = 'gettext';

    /**
     * The gettext domain descriptor.
     *
     * @var GettextDomain
     */
    public readonly GettextDomain $domain;

    /**
     * The locales to process. Combination of supported locales, en_US, and existing .po files.
     *
     * @var array<string> of locales in format 'en_US', 'cs_CZ', ...
     */
    public readonly array $locales;

    public function __construct(GettextDomain $domain)
    {
        global $api;

        $this->domain = $domain;
        $this->checkRequirements();

        $this->locales = array_unique([
            ...$api->locale->supportedLocales,
            'en_US',
            ...$this->findExistingPoLocales($domain->serverOutput),
        ]);
    }

    /**
     * Find locales that already have .po files in the server output directory.
     *
     * @param string $serverOutput
     * @return array<string>
     */
    private function findExistingPoLocales(string $serverOutput): array
    {
        $locales = [];
        foreach (glob($serverOutput . '/*.po') ?: [] as $file) {
            $locales[] = basename($file, '.po');
        }
        return $locales;
    }

    /**
     * Check the requirements for the gettext operations.
     *
     * @throws \RuntimeException if requirements are not met.
     * @return void
     */
    private function checkRequirements(): void
    {
        global $api;

        foreach ($this->domain->folders as $folder) {
            if (!is_dir($folder)) {
                throw new \RuntimeException("The source directory does not exist: " . $folder);
            }
            if (!is_readable($folder)) {
                throw new \RuntimeException("The source directory is not readable: " . $folder);
            }
        }

        $path = $this->domain->serverOutput;
        if (!is_dir($path)) {
            $api->log->info('i18n', "Creating gettext directory: $path");
            mkdir($path, 0777, true) or throw new \RuntimeException("Cannot create gettext directory: " . $path);
        }
        if (!is_dir($path)) {       
            throw new \RuntimeException("The gettext directory does not exist: " . $path);
        }
        if (!is_writable($path)) {
            throw new \RuntimeException("The gettext directory is not writable: " . $path);
        }
        if (!is_readable($path)) {
            throw new \RuntimeException("The gettext directory is not readable: " . $path);
        }

        foreach (['msginit', 'msgmerge', 'msgfmt', 'msgcat'] as $cmd) {
            $cmdReal = trim((string) shell_exec("which $cmd"));
            if (!$cmdReal) {
                throw new \RuntimeException("The command $cmd is not found.");
            }
            if (!is_executable($cmdReal)) {
                throw new \RuntimeException("The command $cmd is not executable.");
            }
        }

        if (!extension_loaded('gettext')) {
            throw new \RuntimeException("The gettext extension is not loaded.");
        }
    }

    /**
     * Find files matching the given file type bitmask across all domain folders.
     *
     * Ignores dot-files and backup extensions. Deduplicates results.
     *
     * @param int $fileTypes Bitmask of FileTypes constants
     * @param array<string> $exclude Patterns to exclude
     * @return array<string> Absolute file paths
     */
    protected function findFiles(int $fileTypes, array $exclude = self::EXCLUDE_FILES): array
    {
        $globs = FileTypes::getGlobs($fileTypes);
        $files = [];

        foreach ($this->domain->folders as $folder) {
            if (!is_dir($folder)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folder, \FilesystemIterator::CURRENT_AS_SELF | \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                $path = "./" . $file->getSubPathname();

                // Skip ignored extensions
                if ($this->fnMatch(self::IGNORED_EXTENSIONS, $path)) {
                    continue;
                }

                if ($this->fnMatch($globs, $path) && !$this->fnMatch($exclude, $path)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return array_unique($files);
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
        global $api;

        $cwd = getcwd() ?: throw new \RuntimeException("Cannot get current working directory");
        chdir(ROOT_DIR);

        $api->log->info('i18n', "🛠️ " . substr($cmd, 0, 127) . (strlen($cmd) > 127 ? "..." : ""), ["cmd" => $cmd]);
        $output = shell_exec($cmd);

        if (is_string($output) || is_null($output)) {
            $api->log->info('i18n', " ┃ $message");
        } else {
            $api->log->error('i18n', " ┃ $cmd output is " . json_encode($output));
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
     * @param string $tags of white space separated tags in format [domain:](attribute|.)[#hash]
     * @return array<string, array{domain: ?string, keyword: string, hash: ?string}> of tag => [domain, keyword, hash]
     */
    protected function parseGettextAttr(string $tags): array
    {
        global $api;

        $list = [];

        foreach (preg_split('/\s+/', $tags) ?: [] as $tag) {
            if (!preg_match(self::TAG_RE, $tag, $matches)) {
                $api->log->error('i18n', "Invalid gettext tag: $tag");
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
