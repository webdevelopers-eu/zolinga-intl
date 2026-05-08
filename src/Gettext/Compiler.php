<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\Models\GettextDocument;
use Zolinga\Intl\Types\FileTypes;
use Zolinga\Intl\Types\GettextModeEnum;

use const Zolinga\System\ROOT_DIR;

/**
 * Compile language po files and translate html files to the supported locales.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class Compiler extends GettextAbstract
{
    /**
     * Generate MO files from translated PO files and translate HTML files if
     * they have <meta name="gettext" content="translate"> or <meta name="gettext" content="cherry-pick">
     *
     * @return void
     */
    public function compile(): void
    {
        global $api;

        $api->log->info('i18n', "📦 Compiling gettext strings in {$this->domain->name} for locales: " . implode(', ', $this->locales));
        $this->compileLanguagePoFiles();
        $this->compileStaticPoFiles();
        $api->locale->initGettext('.static');
        $this->translateHtmlFiles();
    }

    /**
     * Compile language po files: msgmerge with server.pot → temporary .po → msgfmt → .mo.
     *
     * @return void
     */
    private function compileLanguagePoFiles(): void
    {
        global $api;

        foreach ($this->domain->localesWithPO as $lang) {
            $api->log->info('i18n', "🈳 Compiling locale $lang...");
            $poFile = $this->domain->serverOutput . "/$lang.po";
            if (!is_file($poFile) || !is_readable($poFile)) {
                $api->log->warning('i18n', "PO file not found or not readable for locale $lang: $poFile. Skipping compilation for this locale.");
                continue;
            }

            $moFile = $this->domain->serverOutput . "/$lang/LC_MESSAGES/{$this->domain->name}.mo";
            if (!is_dir(dirname($moFile))) {
                mkdir(dirname($moFile), 0777, true) or throw new \RuntimeException("Cannot create directory " . dirname($moFile));
            }

            $tmpPo = tempnam(sys_get_temp_dir(), "{$this->domain->name}.$lang.server.") . '.po';
            $this->exec(
                "msgmerge --no-fuzzy-matching " . escapeshellarg($poFile) . " " . escapeshellarg($this->domain->serverPotFile) . " -o " . escapeshellarg($tmpPo) . " 2>&1",
                "Merging $poFile with server.pot (msgmerge)"
            );
            $this->exec(
                "msgfmt " . escapeshellarg($tmpPo) . " --strict -o " . escapeshellarg($moFile) . " 2>&1",
                "Compiling $tmpPo to $moFile (msgfmt)"
            );
            unlink($tmpPo);

            if (!is_file($moFile)) {
                $api->log->error('i18n', "$moFile not created");
            }

            // List #, fuzzy records
            $contents = (string) file_get_contents($poFile);
            if (strpos($contents, 'fuzzy') !== false) {
                $api->log->error('i18n', "$poFile contains fuzzy translations. Review the translations marked with 'fuzzy' keyword and remove the 'fuzzy' keyword from the translations that are correct.");
            }
        }
    }

    /**
     * Compile static po files: msgmerge with static.pot → temporary .po → msgfmt → .static.mo.
     *
     * @return void
     */
    private function compileStaticPoFiles(): void
    {
        global $api;

        foreach ($this->domain->localesWithPO as $lang) {
            $api->log->info('i18n', "🈳 Compiling static strings for locale $lang...");
            $poFile = $this->domain->serverOutput . "/$lang.po";
            $moFile = $this->domain->serverOutput . "/$lang/LC_MESSAGES/{$this->domain->name}.static.mo";
            if (!is_dir(dirname($moFile))) {
                mkdir(dirname($moFile), 0777, true) or throw new \RuntimeException("Cannot create directory " . dirname($moFile));
            }

            $tmpPo = tempnam(sys_get_temp_dir(), "{$this->domain->name}.$lang.static.") . '.po';
            $this->exec(
                "msgmerge --no-fuzzy-matching " . escapeshellarg($poFile) . " " . escapeshellarg($this->domain->staticPotFile) . " -o " . escapeshellarg($tmpPo) . " 2>&1",
                "Merging $poFile with static.pot (msgmerge)"
            );
            $this->exec(
                "msgfmt " . escapeshellarg($tmpPo) . " --strict -o " . escapeshellarg($moFile) . " 2>&1",
                "Compiling $tmpPo to $moFile (msgfmt)"
            );
            unlink($tmpPo);
        }
    }

    /**
     * Find all html files and translate them to the supported locales.
     *
     * @return void
     */
    private function translateHtmlFiles(): void
    {
        global $api;

        $files = $this->findFiles(FileTypes::HTML, self::EXCLUDE_FILES);
        foreach ($files as $file) {
            if (GettextDocument::getGettextMode($file) === 'translate') {
                foreach ($this->domain->localesWithPO as $locale) {
                    if ($locale !== 'en_US') { // default
                        $api->log->info('i18n', "🈳 Translating $file to $locale...");
                        $targetFile = $this->mkFileName($file, $locale);
                        $this->generateHtmlFile($file, $targetFile, $locale);
                    }
                }
            }
        }
    }

    /**
     *  Create a target document either by replacing the target file or by cherry-picking the translatable nodes.
     * 
     * It will translate the translatable strings from $dictionary and save the result to $targetFile.
     */
    private function generateHtmlFile(string $sourceFile, string $targetFile, string $locale): void
    {
        global $api;

        $oldLocale = $api->locale->locale;
        $api->locale->locale = $locale;
        // We want ::from and not ::tryFrom because at this stage there must be valid gettext mode
        $mode = GettextModeEnum::from(file_exists($targetFile) ? GettextDocument::getGettextMode($targetFile) : 'replace');

        $sourceDoc = new GettextDocument($sourceFile);

        $targetDoc = new GettextDocument($mode === GettextModeEnum::REPLACE || !file_exists($targetFile) ? $sourceFile : $targetFile);
        $targetDoc->gettextMode = $mode;

        try {
            foreach ($targetDoc->translatables as $id => $node) {
                $template = $sourceDoc->translatables[$id];
                if ($template) {
                    $string = $template->gettextString;
                    $domain = $template->gettextDomain . '.static';
                    $translation = dgettext($domain, $string);
                    if ($translation === $string) {
                        $api->log->warning('i18n', "Translation not found for string " . json_encode($string) . " in domain '$domain' for locale '$locale'.");
                        $api->log->tip('i18n', "Check if the string exists in the source document and if it is correctly extracted to the PO file.");
                    } else {
                        $api->log->info('i18n', "Translating node with id $id: '$string' → '$translation'");
                        $node->translate($translation, $template->descendantElements);
                    } 
                } else {
                    $api->log->warning('i18n', "Translatable node with id $id not found in source document for $sourceFile. Skipping $locale translation for this node.");
                }
            }
        } catch (\Exception $e) {
            $api->log->error('i18n', "Error translating $sourceFile to $locale: " . $e->getMessage());
            return;
        }

        $targetDoc->encoding = 'UTF-8';
        $targetDoc->substituteEntities = false;        
        $targetDoc->saveHTMLFile($targetFile);
        $api->locale->locale = $oldLocale;
    }

    private function mkFileName(string $file, string $locale): string
    {
        $path = pathinfo($file);
        return ($path['dirname'] ?? '') . '/' . $path['filename'] . '.' . str_replace('_', '-', $locale) . '.' . ($path['extension'] ?? '');
    }
}
