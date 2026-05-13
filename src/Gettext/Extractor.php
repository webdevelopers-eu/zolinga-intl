<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use DOMAttr;
use DOMCdataSection;
use DOMCharacterData;
use DOMComment;
use DOMElement;
use DOMNodeList;
use DOMText;
use Zolinga\Intl\Models\GettextAttribute;
use Zolinga\Intl\Models\GettextDocument;
use Zolinga\Intl\Models\GettextElement;
use Zolinga\Intl\Types\FileTypes;

/**
 * Extract translatable strings from source files and create language po files.
 * 
 * Splits extraction by file type into separate .pot files, then merges them.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class Extractor extends GettextAbstract
{
    public function extract(): void
    {
        global $api;
        $api->log->info('i18n', "📦 Extracting gettext strings from {$this->domain->name} for locales: " . implode(', ', $this->locales));
        $this->extractServerPotFile();
        $this->extractClientPotFile();
        $this->extractStaticPotFile();
        if ($this->mergePotFiles()) {
            $this->generateLanguagePoFiles();
        }
    }

    private function generateLanguagePoFiles(): void
    {
        global $api;

        $messagesPot = $this->domain->messagesPotFile;
        foreach ($this->locales as $lang) {
            $poFile = $this->domain->serverOutput . "/$lang.po";
            if (!is_file($poFile)) {
                $api->log->info('i18n',  "Creating $poFile");
                $cmd = "msginit --no-translator --input=" . escapeshellarg($messagesPot) . " --locale=" . escapeshellarg($lang) . " --output=" . escapeshellarg($poFile);
            } else {
                $api->log->info('i18n', "Updating $poFile");
                $cmd = "msgmerge --no-fuzzy-matching --previous --backup=none --update " . escapeshellarg($poFile) . " " . escapeshellarg($messagesPot);
            }
            $this->exec("$cmd 2>&1", "Creating $poFile from $messagesPot (msgmerge)");
        }
    }

    /**
     * Extract PHP strings to templates/server.pot.
     */
    private function extractServerPotFile(): void
    {
        $potFile = $this->domain->serverPotFile;
        $this->ensureTemplatesDir();
        // Write minimal header for idempotency (xgettext --join-existing needs existing file)
        $this->initPotFile($potFile);
        $this->extractInBatches(FileTypes::PHP, $potFile, "--add-location -L PHP");
        $this->fixPotfile($potFile);
    }

    /**
     * Extract JS strings to templates/client.pot.
     */
    private function extractClientPotFile(): void
    {
        $potFile = $this->domain->clientPotFile;
        $this->ensureTemplatesDir();
        $this->initPotFile($potFile);
        $this->extractInBatches(FileTypes::JAVASCRIPT, $potFile, "--add-location -L JavaScript --keyword=__ --keyword=_n:1,2");
        $this->fixPotfile($potFile);
    }

    /**
     * Extract HTML strings to templates/static.pot.
     */
    private function extractStaticPotFile(): void
    {
        $potFile = $this->domain->staticPotFile;
        $this->ensureTemplatesDir();
        $this->initPotFile($potFile);

        $files = $this->findFiles(FileTypes::HTML);
        $tmpFile = tempnam(sys_get_temp_dir(), 'gettext-php-strings.tmp') or throw new \RuntimeException("Cannot create temporary file");
        file_put_contents($tmpFile, '<?php' . "\n");
        foreach ($files as $file) {
            file_put_contents($tmpFile, $this->extractHtmlStrings($file), FILE_APPEND);
        }
        $cmd = $this->getExtractCmd([$tmpFile], $potFile, '-L PHP --no-location');
        $this->exec("$cmd 2>&1", "Extracting gettext strings from HTML files...");
        $this->fixPotfile($potFile);
        unlink($tmpFile);
    }

    private function fixPotFile(string $potFile): void
    {
        $this->splitContextInPotFile($potFile);
        // $this->fixEscapedSlashesInPotFile($potFile);
    }

    /**
     * Post-process a .pot file: unescape \/ back to /.
     *
     * xgettext escapes forward slashes as \/ in .pot files. While this is valid
     * C-style escaping, msgmerge treats \< as an invalid control sequence and
     * fails with "invalid control sequence". This method reverts \/ to /.
     *
     * @param string $potFile Absolute path to the .pot file to process.
     */
    // private function fixEscapedSlashesInPotFile(string $potFile): void
    // {
    //     $content = file_get_contents($potFile);
    //     if ($content === false) {
    //         return;
    //     }
    //     $content = str_replace('\\/', '/', $content);
    //     file_put_contents($potFile, $content);
    // }

    /**
     * Issue: gettext does not treat "context\x04message" as a msgctxt + msgid pair
     * 
     * Post-process a .pot file: find msgid values containing the \x04 context
     * separator and rewrite them as proper msgctxt + msgid pairs.
     *
     * Uses GettextPoFile to parse and rewrite the file cleanly.
     *
     * @param string $potFile Absolute path to the .pot file to process.
     */
    private function splitContextInPotFile(string $potFile): void
    {
        global $api;
        $api->log->info('i18n', "Post-processing $potFile to split context and message (if needed)");

        $po = \Zolinga\Intl\GettextPoParser\GettextPoFile::load($potFile);

        $po->fixContext();
        // Always save: the parse+save roundtrip normalizes escapes
        // (e.g. literal BEL \x07 → \u0007) that msgcat would reject.
        $po->save($potFile);
    }

    private function mergePotFiles(): bool
    {
        global $api;

        $templatesDir = $this->domain->serverOutput . '/templates';
        $messagesPot = $this->domain->messagesPotFile;

        $potFiles = glob("$templatesDir/*.pot") ?: [];
        if (!$potFiles) {
            $api->log->warning('i18n', "No .pot files to merge in $templatesDir");
            return false;
        }

        $escaped = array_map('escapeshellarg', $potFiles);
        $cmd = "msgcat -s --use-first " . implode(' ', $escaped) . " -o " . escapeshellarg($messagesPot);
        $this->exec("$cmd 2>&1", "Merging " . count($potFiles) . " .pot files into $messagesPot (msgcat)");

        if (!file_exists($messagesPot)) {
            $api->log->warning('i18n', "Failed to create merged .pot file or no translations found: $messagesPot");
            return false;
        }

        return true;
    }

    private function ensureTemplatesDir(): void
    {
        $dir = $this->domain->serverOutput . '/templates';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true) or throw new \RuntimeException("Cannot create directory: $dir");
        }
    }

    /**
     * Initialize a .pot file with a minimal header for idempotent extraction.
     *
     * xgettext --join-existing requires the file to exist, so we write a minimal
     * header instead of unlinking. This ensures re-runs don't accumulate stale entries.
     */
    private function initPotFile(string $potFile): void
    {
        file_put_contents($potFile, "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Language: en\\n\"\n\n")
            or throw new \RuntimeException("Cannot write to file: $potFile");
    }

    /**
     * Extract translatable strings in batches of 100 files.
     *
     * @param int $fileTypes Bitmask of FileTypes constants
     * @param string $potFile
     * @param string $extraParams
     * @return void
     */
    protected function extractInBatches(int $fileTypes, string $potFile, string $extraParams): void
    {
        $files = $this->findFiles($fileTypes);
        $globs = FileTypes::getGlobs($fileTypes);
        foreach (array_chunk($files, 100) as $filesChunk) {
            $cmd = $this->getExtractCmd($filesChunk, $potFile, $extraParams);
            $this->exec("$cmd 2>&1", "Extracting gettext strings from " . count($filesChunk) . " " . implode(", ", $globs) . " files...");
        }
    }

    /**
     * Get the command to extract the strings from the files.
     *
     * @param array<string> $files
     * @param string $potFile
     * @param string|null $extraParam
     * @return string
     */
    private function getExtractCmd(array $files, string $potFile, ?string $extraParam = null): string
    {
        $filesEsc = array_map('escapeshellarg', $files);

        // xgettext does not support indented HEREDOCs. The workaround is to remove
        // the indentation from the HEREDOCs.
        $fixCmd = "<(sed 's/^[[:space:]]*//' %s)";
        $filesEsc = array_map(fn($file) => sprintf($fixCmd, $file), $filesEsc);

        $cmd = "xgettext " .
            "--add-comments=TRANSLATORS " . // If preceded by comments starting "TRANSLATORS: ", include those
            "--verbose " .
            "--omit-header " .
            "--join-existing --from-code UTF-8 -F --package-version=\"1.0\" " .
            "-o " . escapeshellarg($potFile) . " " .
            "--package-name=" . escapeshellarg($this->domain->name) . " " .
            ($extraParam ?? '') . " " .
            implode(" ", $filesEsc);

        // Run it with bash
        $cmd = "bash -c " . escapeshellarg($cmd);

        return $cmd;
    }

   /**
     * Extract translatable strings from the HTML file.
     * 
     * The format is expected to be: <element gettext="tag tag ...">...</element>
     * 
     * Where tag is a space separated list of [domain:](attribute|.)[#hash]
     *
     * @param string $file
     * @return string
     */
    private function extractHtmlStrings(string $file): string
    {
        global $api;

        if (!in_array(GettextDocument::getGettextMode($file, ''), ['translate'])) {
            $api->log->info('i18n', "Skipping $file because it does not have gettext mode 'translate'");
            return '';
        }

        $doc = new GettextDocument($file);
        $strings = [];
        $changed = false;

        foreach ($doc->translatables as $id => $node) {
            $id = preg_replace('/#[a-z0-9]{6}/', '', $id); // Remove existing hash to avoid duplicates
            $strings[] = $this->makePhpLine($id, $node, "<$doc->filePath>" . " ($id, " . $node->getNodePath() . ")");
            $changed = $node->ensureGettextHash() || $changed;
        }

        if ($changed) {
            $doc->documentElement->setAttribute('lang', 'en-US');
            $doc->save($file);
            $api->log->info('i18n', "Updated $file with gettext-hash attributes for translatable nodes");
        }

        $strings = array_unique($strings);
        return implode("\n", $strings) . "\n";
    }

    /**
     * Generate fake PHP gettext line for virtual file.
     */
    private function makePhpLine(string $id, GettextElement|GettextAttribute $node, string $source): string
    {
        $string = $node->gettextString;
        if ($string === '') {
            return '';
        }

        ["domain" => $domain, "keyword" => $keyword, "hash" => $hash] = GettextDocument::parseTranslatableKey($id);          

        // Normalize spaces:
        $string = trim(preg_replace('/\s+/', ' ', $string));
        $source = trim(preg_replace('/\s+/', ' ', $source));
        $comments = '';

        /** @disregard */
        $isElement = $node instanceof GettextElement;
        $element = $isElement ? $node : $node->ownerElement;
        $comments = [];

        // Describe the element, helps with <button> and such.
        $comments = array_merge(
            $comments, 
            $this->getAdjacentContext($element),
            $this->getCommentsAboutSemanticElement($node)
        );
        $comments[] = "// TRANSLATORS: SOURCE: " . $source;

        // Nested comments
        /** @disregard */
        $xpath = $element->ownerDocument->xpath;
        $comments = array_merge($comments, $this->getNestedTranslatorsComments($element));
        /** @var \DOMXPath $xpath */
        foreach ($xpath->query('ancestor-or-self::*', $element) as $ancestor) {
            /** @var DOMElement $ancestor */
            $comments = array_merge($comments, $this->getPrecedingTranslatorsComments($ancestor));
        }
        $comments = array_unique(array_filter($comments));
        $ret = (empty($comments) ? '' : "// " . ltrim(implode("\n// ", $comments) . "\n")) . "\n";

        if ($domain) {
            $ret .= "dgettext(" . var_export($domain, true) . ", " . var_export($string, true) . ");\n";
        } else {
            $ret .= "_(" . var_export($string, true) . ");\n";
        }

        return $ret;
    }

    private function getCommentsAboutSemanticElement(DOMElement|DOMAttr $node): array
    {
        $comments = [];
        $semanticTags = [
            'button' => 'button / should be concise and as short as possible, follow rules for button text in {{TARGET_LANG}} language', 
            'a' => 'link / if it has "href" attribute apply SEO rules, otherwise treat as generic element',
            'label' => 'form label / should be concise and as short as possible', 
            'option' => 'form option / should be concise and as short as possible', 
            'legend' => 'legend', 
            'th' => 'table header / should be concise and as short as possible',
            'menu' => 'menu / menu item texts should be concise and as short as possible',
            'nav' => 'navigation / navigation item texts should be concise and as short as possible',
            'h1' => 'heading / follow rules for headings or capter titles in {{TARGET_LANG}} langauge',
            'h2' => 'heading / follow rules for headings or capter titles in {{TARGET_LANG}} langauge',
            'h3' => 'heading / follow rules for headings or capter titles in {{TARGET_LANG}} langauge',
            'h4' => 'heading / follow rules for headings or capter titles in {{TARGET_LANG}} langauge',
            'h5' => 'heading / follow rules for headings or capter titles in {{TARGET_LANG}} langauge',
            'h6' => 'heading / follow rules for headings or capter titles in {{TARGET_LANG}} langauge',
            'input' => 'form input / should be short',
            'textarea' => 'form textarea / should be short',
            'cite' => 'citation / should be precise translation of the cited text',
            'abbr' => 'abbreviation',
            'acronym' => 'acronym',
            'quote' => 'quotation / should be precise translation of the quoted text',
            'q' => 'quotation / should be precise translation of the quoted text',
            'p' => 'paragraph / follow rules for general text in {{TARGET_LANG}} language',
            'title' => 'page title / follow rules for web page titles - SEO applies',
            'meta' => 'metadata content / should be concise and SEO-friendly if it is used in title or description meta tags',
        ];
        $element = $node instanceof DOMAttr ? $node->ownerElement : $node;
        // Find the closest semantic ancestor
        $inspect = $element;
        while ($inspect) {
            if (isset($semanticTags[$inspect->tagName])) {
                if ($node instanceof DOMAttr) {
                    $comments[] = "// TRANSLATORS: This string is the value of the '" . $node->name . "' attribute of a <{$inspect->tagName}> element, which is a {$semanticTags[$inspect->tagName]}.";
                } else {                     
                    $comments[] = "// TRANSLATORS: This string is inside a <{$inspect->tagName}> element, which is a {$semanticTags[$inspect->tagName]}.";
                }
                break;
            }
            $inspect = $inspect->parentElement;
        }

        return $comments;
    }

    private function getAdjacentContext(GettextElement $node): array
    {
        $context = [];

        $ctxRange = $node->gettextContextAdjacent;
        if ($ctxRange <= 0) {
            return $context;
        }

        foreach ( ["preceding::*[@gettext]" => -1, "following::*[@gettext]" => 1] as $query => $direction) {
            /** @disregard */
            $list = $node->ownerDocument->xpath->query($query, $node); 

            // DOMNodeList is live and always in document order
            $list = $direction < 0 ? array_reverse(iterator_to_array($list)) : iterator_to_array($list);
            $buffer = [];
            
            foreach ($list as $adjacent) {
                /** @var GettextElement $adjacent */
                if ($adjacent->isTranslatable) {
                    $string = preg_replace('/^.+\x04/', '', $adjacent->gettextString);
                    $buffer[] = "// TRANSLATORS: Adjacent context (line ". ((count($buffer) + 1) * $direction) . "): " . $string;
                    if (count($buffer) >= $ctxRange) {
                        break;
                    }
                }
            }

            if ($direction < 0) {
                $buffer = array_reverse($buffer);
            }

            $context = array_merge($context, $buffer);
        }

        return $context;
    }

    private function getNestedTranslatorsComments(DOMElement $element): array
    {
        /** @disregard */
        $axis = $this->sliceToFirstStopper(iterator_to_array($element->childNodes));
        return $this->extractComments($axis) ?? '';
    }

    private function getPrecedingTranslatorsComments(DOMElement $element): array
    {
        /** @disregard */
        $axis = iterator_to_array($element->ownerDocument->xpath->query('preceding-sibling::node()', $element));
        $axis = array_reverse($this->sliceToFirstStopper(array_reverse($axis)));
        return $this->extractComments($axis) ?? '';
    }

    private function sliceToFirstStopper(array $nodes): array
    {
        $ret = [];
        foreach ($nodes as $node) {
            if (($node instanceof DOMText || $node instanceof DOMCdataSection) && trim($node->textContent) !== '') {
                break; // stop if we hit non-empty text content
            } elseif ($node instanceof DOMElement) {
                break; // stop if we hit a non-comment element
            }
            $ret[] = $node;
        }
        return $ret;
    }

    /** 
     * @param array $axis
     * @return array
     */
    private function extractComments(array $axis): array
    {
        // Include preceding comments
        $comments = [];
        $matched = false;
        foreach ($axis as $node) {
            $text = trim($node->textContent);

            if (!$matched && str_starts_with($text, 'TRANSLATORS:')) {
                $matched = true;
            }

            if ($matched && !empty($text)) {
                $comments = array_merge($comments, explode("\n", trim($text)));
            }
        }

        $comments = array_filter(array_map(fn($c) => trim($c), $comments));
        return $comments;
    }
}
