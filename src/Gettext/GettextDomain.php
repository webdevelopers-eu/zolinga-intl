<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\Types\FileTypes;
use Zolinga\System\Types\SeverityEnum;

/**
 * Domain descriptor for gettext operations.
 *
 * Describes a gettext domain: its name, folders to scan, file types to extract,
 * and output paths for server-side (.pot/.po/.mo) and client-side (.json) files.
 */
class GettextDomain implements \Stringable
{
    /**
     * @param string $name Domain name (e.g. 'acme-module', 'default', 'system')
     * @param string $serverOutput Path for .pot/.po/.mo files (PHP + HTML)
     * @param string $clientJsonOutput Path for JS .json dictionaries
     * @param array<string> $folders Absolute paths to scan for translatable strings
     * @param int $fileTypes Bitmask of FileTypes constants
     */
    public function __construct(
        public readonly string $name,
        public readonly string $serverOutput,
        public readonly string $clientJsonOutput,
        public readonly array $folders = [],
        public readonly int $fileTypes = FileTypes::ALL,
    ) {}

    /** Path to the server-side .pot template (PHP strings only) */
    public string $serverPotFile { get => $this->serverOutput . '/templates/server.pot'; }

    /** Path to the client-side .pot template (JS strings only) */
    public string $clientPotFile { get => $this->serverOutput . '/templates/client.pot'; }

    /** Path to the static .pot template (HTML strings only) */
    public string $staticPotFile { get => $this->serverOutput . '/templates/static.pot'; }

    /** Path to the merged messages.pot */
    public string $messagesPotFile { get => $this->serverOutput . '/messages.pot'; }

    public array $localesWithPO { get => $this->localesWithPO ??= $this->initLocalesWithPO(); }

    private function initLocalesWithPO(): array
    {
        global $api;

        $locales = [];

        // Find all ??-??.po files
        foreach (glob($this->serverOutput . '/??_??.po') ?: [] as $file) {
            $fileLang = basename($file, '.po');
            $lang = \Locale::getPrimaryLanguage($fileLang) . "_" . \Locale::getRegion($fileLang);
            $locales[$lang] = $lang;
        }

        $api->log->log(
            empty($locales) ? SeverityEnum::WARNING : SeverityEnum::INFO, 
            'i18n', 
            "{$this}: Found " . count($locales) . " locales with PO files: " . (implode(', ', $locales) ?: 'none')
        );

        return array_values($locales);
    }

    public function __toString(): string
    {
        // 🈯 🈳 🈵
        return "🈯 Gettext[{$this->name}]";
    }
}
