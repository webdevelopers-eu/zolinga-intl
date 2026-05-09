<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

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
                $cmd = "msgmerge --previous --update " . escapeshellarg($poFile) . " " . escapeshellarg($messagesPot);
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
     * xgettext extracts "context\x04message" as a single msgid; this method
     * converts those entries to the standard POT form:
     *
     *   msgctxt "context"
     *   msgid "message"
     *
     * @param string $potFile Absolute path to the .pot file to process.
     */
    private function splitContextInPotFile(string $potFile): void
    {
        global $api;
        $api->log->info('i18n', "Post-processing $potFile to split context and message (if needed)");
        
        $content = file_get_contents($potFile);
        if ($content === false) {
            return;
        }

        // Match a complete POT entry block. We look for msgid "..." lines where
        // the assembled value contains the \x04 byte and rewrite them.
        // POT msgid values may be split across multiple "..." continuation lines.
        $changed = false;
        $content = preg_replace_callback(
            '/^(msgid\s+(?:"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"\s*)+)/m',
            function (array $m) use (&$changed): string {
                $block = $m[1];

                // Assemble the raw string value from all quoted segments.
                $assembled = '';
                preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/s', $block, $parts);
                foreach ($parts[1] as $segment) {
                    // Unescape xgettext C-style escapes to get the real bytes.
                    $assembled .= stripcslashes($segment);
                }

                $sepPos = strpos($assembled, "\x04");
                if ($sepPos === false) {
                    return $block; // no context separator – leave untouched
                }

                $changed = true;
                $ctx = substr($assembled, 0, $sepPos);
                $msg = substr($assembled, $sepPos + 1);

                return 
                    'msgctxt ' . json_encode($ctx) . "\n" . 
                    'msgid ' . json_encode($msg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            },
            $content
        );

        if ($changed) {
            file_put_contents($potFile, $content);
        }
    }

    /**
     * Merge all three template .pot files into messages.pot via msgcat.
     */
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
            $strings[] = $this->makePhpLine($id, $node, "<$doc->filePath>" . " ($id)");
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

        $element = $node instanceof GettextElement ? $node : $node->ownerElement;

        // Nested comments
        $axis = $this->sliceToFirstStopper(iterator_to_array($element->childNodes));
        $comments .= $this->extractComments($axis) ?? '';

        // Preceeding comments
        $comments .= $this->getPrecedingTranslatorsComments($element);

        // Top-level comments before <body> | <html> | <head> | <article> | <section> (all of them combined)
        $xpath = 'ancestor::body | ancestor::html | ancestor::head | ancestor::article | ancestor::section';
        /** @disregard */
        foreach ($element->ownerDocument->xpath->query($xpath, $element) as $ancestor) {
            /** @var DOMElement $ancestor */
            $comments .= $this->getPrecedingTranslatorsComments($ancestor);
        }

        $ret = "$comments// " . ($comments ? '' : 'TRANSLATORS: ') . "SOURCE: " . $node->getNodePath() . " " . addcslashes($source, "\n\r\t") . "\n";

        if ($domain) {
            $ret .= "dgettext(" . var_export($domain, true) . ", " . var_export($string, true) . ");\n";
        } else {
            $ret .= "_(" . var_export($string, true) . ");\n";
        }

        return $ret;
    }

    private function getPrecedingTranslatorsComments(?DOMElement $element): string
    {
        if (!$element) {
            return '';
        }

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
     * @return string|null
     */
    private function extractComments(array $axis): ?string
    {
        // Include preceding comments
        $comments = "";
        $matched = false;
        foreach ($axis as $node) {
            $text = trim($node->textContent);

            if (!$matched && str_starts_with($text, 'TRANSLATORS:')) {
                $matched = true;
            }

            if ($matched && !empty($text)) {
                $comments .= $text . "\n";
            }
        }

        $comments = explode("\n", trim($comments));
        $comments = array_filter(array_map(fn($c) => trim($c), $comments));
        $comments = implode("\n// ", $comments);
        $comments = $comments ? "// " . $comments . "\n" : "";
        return $comments ?: null;
    }
}
