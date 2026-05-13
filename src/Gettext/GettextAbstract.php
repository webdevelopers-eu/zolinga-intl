<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\Types\FileTypes;
use Zolinga\System\Types\SeverityEnum;

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
            ...$this->domain->localesWithPO,
        ]);
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
        global $api;

        if ($this->domain->name !== 'test') { // Exclude test domain from translation in non-test domains
            $exclude[] = '*/gettext-test/*';
        }

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

                if ($this->fnMatch($globs, $path) && !$this->fnMatch($exclude, $path)) {
                    // Skip localized files
                    if (preg_match('/\.[a-z]{2}[_-][A-Z]{2}\.[^\.]+$/', $file->getBasename())) {
                        $api->log->info('i18n', "Skipping localized file: $path");
                        continue;
                    }

                    // Skip ignored extensions
                    if ($this->fnMatch(self::IGNORED_EXTENSIONS, $path)) {
                        $api->log->info('i18n', "Skipping ignored file: $path");
                        continue;
                    }

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

    protected function exec(string $cmd, string $message, ?string &$output = null): bool
    {
        global $api;

        $cwd = getcwd() ?: throw new \RuntimeException("Cannot get current working directory");
        chdir(ROOT_DIR);

        $api->log->info('i18n', $message);
        // $api->log->info('i18n', "🛠️ " . substr($cmd, 0, 127) . (strlen($cmd) > 127 ? "..." : ""), ["cmd" => $cmd]);
        $api->log->info('i18n', "🛠️ $cmd");

        exec("$cmd 2>&1", $outputLines, $returnCode);
        $status = $returnCode === 0 ? SeverityEnum::INFO : SeverityEnum::ERROR;

        foreach ($outputLines as $line) {
            $api->log->log($status, 'i18n', " ┃ $line");
        }
        chdir($cwd);

        $output = implode("\n", $outputLines);

        return $status === SeverityEnum::INFO;
    }
}
