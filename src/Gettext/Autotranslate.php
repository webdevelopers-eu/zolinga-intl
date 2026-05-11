<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\GettextPoParser\GettextPoFile;
use Zolinga\Intl\Types\GettextTemplateEnum;

/**
 * Autotranslate untranslated gettext entries using the AI translator service.
 *
 * Extends GettextAbstract to reuse domain/locale setup, file scanning, and
 * CLI tool execution.
 *
 * Usage (via CLI):
 *   bin/zolinga gettext:autotranslate [--domains=module1,module2]
 */
class Autotranslate extends GettextAbstract
{
    /**
     * Translate all untranslated entries for all locales in the domain.
     *
     * Skips en_US (source language). Saves progress after each translation
     * to a .autotranslate file. On completion, merges translations back into
     * the original .po file using msgmerge --compendium.
     */
    public function autotranslate(): void
    {
        global $api;

        foreach ($this->locales as $locale) {
            if ($locale === 'en_US') {
                continue;
            }

            $poPath = $this->domain->serverOutput . "/{$locale}.po";
            $autoPath = $poPath . '.autotranslate';

            // Determine source file
            if (file_exists($autoPath)) {
                $api->log->info('i18n', "{$this->domain}: Picking up from previous autotranslate session: $autoPath");
                $sourcePath = $autoPath;
            } elseif (file_exists($poPath)) {
                $sourcePath = $poPath;
            } else {
                $api->log->warning('i18n', "{$this->domain}: No PO file found for locale $locale, skipping");
                continue;
            }

            $po = GettextPoFile::load($sourcePath);
            $untranslated = $po->getUntranslatedEntries();
            $api->log->info('i18n', "{$this->domain}/{$locale}: Translating " . count($untranslated) . " untranslated entries");

            foreach ($untranslated as $entry) {
                if ($entry->isPlural) {
                    $context = "Translate all $po->nplurals plural forms determined by this formula: form number = $po->plural . ";
                    $context.= "`msgstr[0]` is a singular translation of ". GettextPoFile::poEncode($entry->msgid) . " ";
                    $context.= "and " . implode(', ', array_map(fn($n) => "`msgstr[$n]`", range(1, $po->nplurals - 1))) . " are various plural form translations of " . GettextPoFile::poEncode($entry->msgidPlural);
                } else {
                    $context = "";
                }
                $retries = 3;
                do {
                    try {
                        $translated = $api->translator->translate(
                            string: $entry->toPoString(),
                            fromLang: 'en_US',
                            toLang: $locale,
                            context: $context,
                            template: GettextTemplateEnum::GETTEXT_ENTRY,
                        );

                        $translatedEntry = $po->parseToEntry($translated);
                        foreach($entry->msgstr as $index => $str) {
                            $this->validateTranslationOrThrow(
                                !$index ? $entry->msgid : $entry->msgidPlural, 
                                $translatedEntry->msgstr[$index] ?? ''
                            );
                        }
                        $entry->translate($translatedEntry->msgstr);
                        $po->save($autoPath);
                    } catch (\Throwable $e) {
                        $api->log->error('i18n', "{$this->domain}/{$locale}: Failed to translate entry '{$entry->msgid}': $entry . Error: " . $e->getMessage());
                    }
                } while ($retries-- > 0 && !$entry->isTranslated);
                if (!$entry->isTranslated) {
                    $api->log->error('i18n', "{$this->domain}/{$locale}: Failed to translate entry '{$entry->msgid}' after multiple attempts, skipping.");
                }
            }

            $this->mergeBack($poPath, $autoPath);
        }
    }

    private function validateTranslationOrThrow(string $original, string $translated): void
    {
        if (empty(trim($translated))) {
            throw new \Exception("Translation API returned empty string for original: $original");
        }

        // Match all special placeholders <...> and %\w+ and ${...} and {{...}} and $\w+ and ensure they are present in the translation
        preg_match_all('/(?<placeholders><[^>]+>|%\w+|\${[^}]+}|{{[^}]+}}|\$\w+)/', $original, $matches);
        $placeholders = $matches['placeholders'] ?? [];
        foreach ($placeholders as $ph) {
            if (strpos($translated, $ph) === false) {
                throw new \Exception("Translation is missing placeholder '$ph' from original: $original. Translated text: $translated");
            }
        }
    }

    /**
     * Merge the .autotranslate file back into the original .po file.
     *
     * Uses msgmerge --compendium so that existing translations in the
     * original .po file take precedence, and only missing translations
     * are filled in from the autotranslate file.
     */
    private function mergeBack(string $poPath, string $autoPath): void
    {
        global $api;

        if (!file_exists($autoPath)) {
            return;
        }

        $messagesPot = $this->domain->messagesPotFile;

        if (!file_exists($messagesPot)) {
            $api->log->warning('i18n', "{$this->domain}: messages.pot not found at $messagesPot, cannot merge autotranslate");
            return;
        }

        $cmd = sprintf(
            'msgmerge --compendium %s %s %s -o %s',
            escapeshellarg($autoPath),
            escapeshellarg($poPath),
            escapeshellarg($messagesPot),
            escapeshellarg($poPath),
        );

        $success = $this->exec($cmd, "{$this->domain}: Merging autotranslate back into $poPath");

        if ($success) {
            unlink($autoPath);
            $api->log->info('i18n', "{$this->domain}: Removed autotranslate file: $autoPath");
        }
    }
}
