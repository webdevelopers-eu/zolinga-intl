<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\Types\FileTypes;
use const Zolinga\System\ROOT_DIR;

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
        $this->mergePotFiles();
        $this->generateLanguagePoFiles();
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
    }

    /**
     * Merge all three template .pot files into messages.pot via msgcat.
     */
    private function mergePotFiles(): void
    {
        global $api;

        $templatesDir = $this->domain->serverOutput . '/templates';
        $messagesPot = $this->domain->messagesPotFile;

        $potFiles = glob("$templatesDir/*.pot") ?: [];
        if (!$potFiles) {
            $api->log->warning('i18n', "No .pot files to merge in $templatesDir");
            return;
        }

        $escaped = array_map('escapeshellarg', $potFiles);
        $cmd = "msgcat --use-first " . implode(' ', $escaped) . " -o " . escapeshellarg($messagesPot);
        $this->exec("$cmd 2>&1", "Merging " . count($potFiles) . " .pot files into $messagesPot (msgcat)");
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
            "--verbose " .
            "--omit-header " .
            "--join-existing --from-code UTF-8 -F --package-version=\"1.0\" " .
            "-o " . escapeshellarg($potFile) . " " .
            "--package-name=\"{$this->domain->name}\" " .
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

        if (!in_array($this->getGettextMode($file, ''), ['translate'])) {
            return '';
        }
        $api->log->info('i18n', "📄 Preparing extraction from $file");

        $relativeFile = $api->fs->toZolingaUri($file);
        $strings = [];
        $doc = $this->loadHtmlFile($file);

        foreach ($this->findTranslatables($doc) as $node) {
            foreach ($this->parseGettextAttr($node->nodeValue) as ['domain' => $domain, 'keyword' => $keyword, 'hash' => $hash]) {
                if ($hash) {
                    // Already translated
                    continue;
                } elseif ($keyword == '.') {
                    $strings[] = $this->makePhpLine($domain ?: null, $node->ownerElement->nodeValue, "$relativeFile: Text content of " . $doc->saveXML($node));
                } else {
                    $strings[] = $this->makePhpLine($domain ?: null, $node->ownerElement->getAttribute($keyword), "$relativeFile: Attr $keyword of " . $doc->saveXML($node));
                }
            }
        }
        $strings = array_unique($strings);
        return implode("\n", $strings) . "\n";
    }

    /**
     * Generate fake PHP gettext line for virtual file.
     *
     * @param string|null $domain
     * @param string $string
     * @param string $comment
     * @return string
     */
    private function makePhpLine(?string $domain, string $string, string $comment): string
    {
        // Normalize spaces:
        $string = trim(preg_replace('/\s+/', ' ', $string));
        $comment = trim(preg_replace('/\s+/', ' ', $comment));

        if ($string === '') {
            return '';
        }

        $ret = "// " . addcslashes($comment, "\n\r\t") . "\n";
        if ($domain) {
            $ret .= "dgettext(" . json_encode($domain) . ", " . json_encode($string) . ");\n";
        } else {
            $ret .= "_(" . json_encode($string) . ");\n";
        }

        return $ret;
    }
}
