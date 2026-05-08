<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use DOMDocument;
use Zolinga\Intl\Types\FileTypes;
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
            if ($this->getGettextMode($file) === 'translate') {
                ['template' => $templateDoc, 'dictionary' => $dictionary] = $this->buildFileDictionary($file);

                $defaultDomain = parse_url($api->fs->toZolingaUri($file), PHP_URL_HOST) ?: 'default';

                foreach ($this->domain->localesWithPO as $locale) {
                    $api->log->info('i18n', "🈳 Translating $file to $locale...");
                    if ($locale !== 'en_US') { // default
                        $targetFile = $this->mkFileName($file, $locale);
                        $this->generateHtmlFile($targetFile, $locale, $defaultDomain, $templateDoc, $dictionary);
                    }
                }
            }
        }
    }

    /**
     *  Create a target document either by replacing the target file or by cherry-picking the translatable nodes.
     * 
     * It will translate the translatable strings from $dictionary and save the result to $targetFile.
     * 
     * @param  string $targetFile the target file to be created
     * @param  string $locale the target locale
     * @param  string $defaultDomain the default domain for the translation
     * @param  DOMDocument $templateDoc the template document
     * @param  array<string, string> $dictionary the dictionary of "DOMAIN:#HASH" => STRID
     * @return void
     */
    private function generateHtmlFile(string $targetFile, string $locale, string $defaultDomain, DOMDocument $templateDoc, array $dictionary): void
    {
        global $api;

        $oldLocale = $api->locale->locale;
        $api->locale->locale = $locale;
        $mode = file_exists($targetFile) ? $this->getGettextMode($targetFile) : 'replace';


        switch ($mode) {
            case 'replace':
                $api->log->info('i18n', "📄 Replacing $targetFile ($locale)");
                $targetDoc = $templateDoc->cloneNode(true);
                foreach ($targetDoc->getElementsByTagName('meta') as $el) {
                    if ($el->getAttribute('name') === 'gettext') {
                        $el->setAttribute('content', 'replace');
                    }
                }
                break;
            case 'cherry-pick':
                $api->log->info('i18n', "📝 Cherry-picking $targetFile ($locale)");
                $targetDoc = $this->loadHtmlFile($targetFile);
                break;
            default:
                $api->log->error('i18n', "$targetFile: gettext meta not set or invalid in file $targetFile: " . json_encode($mode) . " Expected <meta name='gettext' content='replace|cherry-pick'/>");
                return;
        }
        $this->translateHtmlStrings($targetDoc, $dictionary, $defaultDomain);

        $targetDoc->encoding = 'UTF-8';
        $targetDoc->substituteEntities = false;        
        $targetDoc->saveHTMLFile($targetFile);
        $api->locale->locale = $oldLocale;
    }

    /**
     * Translate all translatable strings from $doc using $dictionary.
     * 
     * Dictionary holds keys in the format "DOMAIN:#HASH" => STRID. We use preferably the HASH if it exists in @gettext attribute.
     * 
     * If the $doc @gettext attribute does not have HASH that means that user modified translated document (the "cherry-pick" mode) 
     * and we need to calculate the HASH from the string. The string is expected to be English strid initially.
     * 
     * It will modify the input $doc.
     *
     * @param DOMDocument $doc the target document to be translated. It will be modified.
     * @param array<string,string> $dictionary the dictionary of "DOMAIN:#HASH" => STRID
     * @param string $defaultDomain the default domain for the translation
     * @return void
     */
    private function translateHtmlStrings(DOMDocument $doc, array $dictionary, string $defaultDomain): void {
        global $api;

        foreach ($this->findTranslatables($doc, true) as $node) {
            $tags = [];
            foreach ($this->parseGettextAttr($node->nodeValue) as $tag => ['domain' => $domain, 'keyword' => $keyword, 'hash' => $hash]) {
                if (!$hash) { // new translation
                    $strId = $keyword == '.' ? $node->ownerElement->nodeValue : $node->ownerElement->getAttribute($keyword);
                    $hash = $this->calculateHash($strId);
                    $dictionary["$domain:#$hash"] = $strId;
                } else {
                    $strId = $dictionary["$domain:#$hash"] ?? '';
                }
                if (!$strId) {
                    $api->log->error('i18n', "". $doc->documentURI .": $keyword#$hash not found in dictionary: " . $doc->saveXML($node->ownerElement). " Was the corresponding string removed from the source file? You can copy the missing element from source file into translation file. ");
                    $tags[] = $tag;
                    continue;
                }
                $resolvedDomain = $domain ?: $defaultDomain;
                $translated = dgettext($resolvedDomain . '.static', $strId);

                if ($keyword == '.') {
                    $node->ownerElement->nodeValue = $translated;
                } else {
                    $node->ownerElement->setAttribute($keyword, $translated);
                }

                $tags[] = ($domain ? "$domain:" : '') . "$keyword#$hash";
            }
            $node->nodeValue = implode(' ', $tags);
        }
    }

    /**
     * Opens source file and builds a template by adding hashes to @gettext attributes.
     * 
     * Also builds an array of "DOMAIN:#HASH" => STRID for the translation process.
     *
     * @param mixed $file
     * @return array{template: DOMDocument, dictionary: array<string, string>}
     */
    private function buildFileDictionary($file): array
    {
        global $api;

        $doc = $this->loadHtmlFile($file);
        $dictionary = [];
        foreach ($this->findTranslatables($doc) as $node) {
            $tags = [];
            foreach ($this->parseGettextAttr($node->nodeValue) as ['domain' => $domain, 'keyword' => $keyword, 'hash' => $hash]) {
                if ($hash) {
                    $api->log->error('i18n', "$file: $keyword already translated in source $file: " . $doc->saveXML($node->ownerElement));
                    continue;
                }
                $string = $this->normalizeString($keyword == '.' ? $node->ownerElement->nodeValue : $node->ownerElement->getAttribute($keyword));
                $hash = $this->calculateHash($string);
                $dictionary["$domain:#$hash"] = $string;
                $tags[] = ($domain ? "$domain:" : '') . "$keyword#$hash";
            }
            $node->nodeValue = implode(' ', $tags);
        }

        return ['template' => $doc, 'dictionary' => $dictionary];
    }

    private function normalizeString(string $string): string
    {
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    private function mkFileName(string $file, string $locale): string
    {
        $path = pathinfo($file);
        return ($path['dirname'] ?? '') . '/' . $path['filename'] . '.' . str_replace('_', '-', $locale) . '.' . ($path['extension'] ?? '');
    }
}
