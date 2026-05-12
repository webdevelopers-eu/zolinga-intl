<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\GettextPoParser\GettextPoEntry;
use Zolinga\Intl\GettextPoParser\GettextPoFile;
use Zolinga\Intl\Types\GettextTemplateEnum;
use Zolinga\System\Types\SeverityEnum;

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
        foreach ($this->locales as $locale) {
            if ($locale === 'en_US') {
                continue;
            }
            $this->autotranslateLocale($locale);
        }
    }

    /**
     * Autotranslate all untranslated entries for a single locale.
     */
    private function autotranslateLocale(string $locale): void
    {
        global $api;

        $poPath = $this->domain->serverOutput . "/{$locale}.po";
        $autoPath = $poPath . '.autotranslate';

        $sourcePath = $this->resolveSourcePath($poPath, $autoPath);
        if ($sourcePath === null) {
            $api->log->warning('i18n', "{$this->domain}: No PO file found for locale $locale, skipping");
            return;
        }

        $po = GettextPoFile::load($sourcePath);
        $untranslated = $po->getUntranslatedEntries();
        $api->log->info('i18n', "{$this->domain}/{$locale}: Translating " . count($untranslated) . " untranslated entries");

        foreach ($untranslated as $entry) {
            $this->autotranslateEntry($po, $entry, $locale, $autoPath);
        }

        $this->mergeBack($poPath, $autoPath);
    }

    /**
     * Resolve which PO file to use as source.
     *
     * Prefers .autotranslate (resume), then .po, returns null if neither exists.
     */
    private function resolveSourcePath(string $poPath, string $autoPath): ?string
    {
        global $api;

        if (file_exists($autoPath)) {
            $api->log->info('i18n', "{$this->domain}: Picking up from previous autotranslate session: $autoPath");
            return $autoPath;
        }
        if (file_exists($poPath)) {
            return $poPath;
        }
        return null;
    }

    /**
     * Translate a single PO entry with retry logic.
     */
    private function autotranslateEntry(GettextPoFile $po, object $entry, string $locale, string $autoPath): void
    {
        global $api;

        $contextMain =
            ltrim(($api->config['intl']['translate']['instructions']['*'] ?? '') . "\n") .
            ltrim(($api->config['intl']['translate']['instructions'][$locale] ?? '') . "\n") .
            ($entry->isPlural ? $this->buildPluralContext($po, $entry) : '');
        $retries = 7;
        $instructions = [];

        do {
            try {
                $context = trim($contextMain . rtrim("\n - " . implode("\n - ", $instructions), "\n -"));
                $translatedEntry = $entry->isSingular ? $this->translateSingular($po, $entry, $locale, $context) : $this->translatePlural($po, $entry, $locale, $context);
                $newInstructions = $this->collectInstruct($entry, $translatedEntry);

                if (!empty($newInstructions)) {
                    $instructions = array_unique(array_merge($instructions, $newInstructions));
                    throw new \Exception("Translation failed with " . count($newInstructions) . " issues.");
                }

                $entry->translate($translatedEntry->msgstr);
                $po->save($autoPath);
                return;
            } catch (\Throwable $e) {
                $api->log->error('i18n', "{$this->domain}/{$locale}: Failed to translate entry '{$entry->msgid}': " . $e->getMessage());
            }
        } while ($retries-- > 0 && !$entry->isTranslated);

        if (!$entry->isTranslated) {
            $api->log->error('i18n', "{$this->domain}/{$locale}: Failed to translate entry '{$entry->msgid}' after multiple attempts, skipping.");
        }
    }

    private function translateSingular(GettextPoFile $po, GettextPoEntry $entry, string $locale, string $context): GettextPoEntry {
        global $api;
        
        $comments = implode("\n", $entry->translatorComments);

        $translated = $api->translator->translate(
            string: $entry->msgid,
            fromLang: 'en_US',
            toLang: $locale,
            context: trim($context . "\n" . $comments),
            template: GettextTemplateEnum::DEFAULT,
        );
        
        $newEntry = $po->parseToEntry($entry->toPoString()); // or `clone $entry`? Which is future proof?
        $newEntry->translate($translated);

        return $newEntry;
    }

    private function translatePlural(GettextPoFile $po, GettextPoEntry $entry, string $locale, string $context): GettextPoEntry {
        global $api;

        $pluralExamples = $po->pluralCountExamples;
        $pluralInstructions = implode("\n", array_map(
            fn($n, $form) => "Use plural form `msgstr[$form]` to represent the plural case for count n=" . implode(', ', $n) . " and so on. Do not replace placeholders with these numbers!",
            $pluralExamples,
            array_keys($pluralExamples)
        )) . "\n";

        // We got best results with translategemme by passing the whole thing - the multiple plurals were big issue
        // when translated separately, because the AI lost track of which plural form is which.  
        $translated = $api->translator->translate(
            string: $entry->toPoString(),
            fromLang: 'en_US',
            toLang: $locale,
            context: $pluralInstructions . $context,
            template: GettextTemplateEnum::GETTEXT_ENTRY,
        );

        $newEntry = $po->parseToEntry($translated);
        return $newEntry;
    }

    /**
     * Build context string for plural entries.
     */
    private function buildPluralContext(GettextPoFile $po, object $entry): string
    {
        $context = "Translate all $po->nplurals plural forms determined by this formula: $po->plural. ";
        $context .= "`msgstr[0]` is a singular translation of " . GettextPoFile::poEncode($entry->msgid) . " ";
        $context .= "and " . implode(', ', array_map(fn($n) => "`msgstr[$n]`", range(1, ($po->nplurals ?? 2) - 1)))
            . " are various plural form translations of " . GettextPoFile::poEncode($entry->msgidPlural);
        return $context;
    }

    /**
     * Collect fix instructions for all msgstr indices of a translated entry.
     *
     * @return array<string> Empty if translation is valid.
     */
    private function collectInstruct(object $entry, object $translatedEntry): array
    {
        $instructions = [];

        foreach ($entry->msgstr as $index => $str) {
            $keyword = "msgstr" . ($entry->isPlural ? "[$index]" : "");
            $original = intval($index ?: 0) === 0 ? $entry->msgid : $entry->msgidPlural;
            $instructions = array_unique(array_merge(
                $instructions,
                $this->getInstrucForSingle($keyword, $original, $translatedEntry->msgstr[$index] ?? '')
            ));
        }

        return $instructions;
    }

    private function getInstrucForSingle(string $keyword, string $original, string $translated): array
    {
        global $api;

        $instructions = [];

        if (empty(trim($translated))) {
            $instructions[] = "Translate all including `$keyword`!";
        }

        // Match sprintf() format specifiers and other placeholders that must be preserved
        preg_match_all(
            '/(?<placeholders>'
                . '<\/?[0-9a-zA-Z][^>]*>'  // HTML/XML tags like <1>, </b>, <div>
                . '|%(?:%|(?:\d+\$)?[+\-]?(?:[0 ]|\'.)?-?\d*(?:\.\d+)?[bcdeEfFgGosuxX])'  // sprintf: %d, %1$s, %.2f, %%, etc.
                . '|\${[^}]+}'              // ${var} template variables
                . '|{{[^}]+}}'              // {{var}} template variables
                . '|\$[a-zA-Z][a-zA-Z0-9]*' // $varName except currency formats like $100
                . ')/',
            $original,
            $matches
        );
        $placeholders = $matches['placeholders'] ?? [];
        foreach ($placeholders as $ph) {
            if (strpos($translated, $ph) === false) {
                $instructions[] = "The placeholder '$ph' must be present in the translation of the `$keyword` line. DO NOT REMOVE IT OR REPLACE IT with numbers or anything else.";
            }
        }

        $icon = empty($instructions) ? '✅' : '⚠️';
        $api->log->log(
            $instructions ? SeverityEnum::WARNING : SeverityEnum::INFO, 
            'i18n', 
            "✨ Translation: \n$icon Original: $original \n$icon Translat: $translated \nIssues: " . ($instructions ? implode(" ", $instructions) :  'none')
        );

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
