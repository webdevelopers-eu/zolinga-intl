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
                $newInstructions = [];
                do {
                    try {
                        $translated = $api->translator->translate(
                            string: $entry->toPoString(),
                            fromLang: 'en_US',
                            toLang: $locale,
                            context: trim($context . rtrim("\n - " . implode("\n - ", $newInstructions), "\n -")),
                            template: GettextTemplateEnum::GETTEXT_ENTRY,
                        );

                        $translatedEntry = $po->parseToEntry($translated);
                        $oldInstructions = $newInstructions;
                        $newInstructions = [];
                        foreach($entry->msgstr as $index => $str) {
                            $newInstructions = array_unique(array_merge($newInstructions, $this->getFixInstructions(
                                "msgstr" . ($entry->isPlural ? "[$index]" : ""),
                                !$index ? $entry->msgid : $entry->msgidPlural, 
                                $translatedEntry->msgstr[$index] ?? '',
                            )));
                        }
                        if (!empty($newInstructions)) {
                            $newInstructions = array_unique(array_merge($oldInstructions, $newInstructions));
                            throw new \Exception("Translation for entry '{$entry->msgid}' is missing required placeholders. Instructions for translator: $instructions");
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

    private function getFixInstructions(string $keyword, string $original, string $translated): array
    {
        $instructions = [];

        if (empty(trim($translated))) {
            $instructions[] = "Translate all including $keyword!";
        }

        // Match sprintf() format specifiers and other placeholders that must be preserved
        preg_match_all(
            '/(?<placeholders>'
                . '<\/?[0-9a-zA-Z][^>]*>'  // HTML/XML tags like <1>, </b>, <div>
                . '|%(?:%|(?:\d+\$)?[+\-]?(?:[0 ]|\'.)?-?\d*(?:\.\d+)?[bcdeEfFgGosuxX])'  // sprintf: %d, %1$s, %.2f, %%, etc.
                . '|\${[^}]+}'              // ${var} template variables
                . '|{{[^}]+}}'              // {{var}} template variables
                . '|\$[a-zA-Z0-9]*'         // $varName
                . ')/',
            $original,
            $matches
        );
        $placeholders = $matches['placeholders'] ?? [];
        foreach ($placeholders as $ph) {
            if (strpos($translated, $ph) === false) {
                $instructions[] = "Make sure to include the placeholder '$ph' in the $keyword.";
            }
        }

        return $instructions; 
    }

    /**
     * Merge the .autotranslate file back into the original .po file.
     *
     * Only fills in entries that are untranslated in the original .po.
     * Existing translations in the original are never overwritten.
     */
    private function mergeBack(string $poPath, string $autoPath): void
    {
        global $api;

        if (!file_exists($autoPath)) {
            return;
        }

        $po = GettextPoFile::load($poPath);
        $autoPo = GettextPoFile::load($autoPath);

        $merged = 0;
        foreach ($po->getUntranslatedEntries() as $entry) {
            $key = $entry->context !== null
                ? $entry->context . "\x04" . $entry->msgid
                : $entry->msgid;

            $autoEntries = $autoPo->find(
                $entry->msgid,
                $entry->context
            );

            foreach ($autoEntries as $autoEntry) {
                if ($autoEntry->isTranslated) {
                    $entry->translate($autoEntry->msgstr);
                    $merged++;
                    break;
                }
            }
        }

        if ($merged > 0) {
            $po->save($poPath);
            $api->log->info('i18n', "{$this->domain}: Merged $merged translations from autotranslate into $poPath");
        }

        unlink($autoPath);
        $api->log->info('i18n', "{$this->domain}: Removed autotranslate file: $autoPath");
    }
}
