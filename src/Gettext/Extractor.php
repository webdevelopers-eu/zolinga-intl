<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Zolinga\Intl\GettextCli;

/**
 * Extract translate-able strings from the source files and create language po files.
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class Extractor extends GettextAbstract
{
    public function extract(): void
    {
        GettextCli::log("📦 Extracting gettext strings from $this->modulePath for locales: " . implode(', ', $this->locales) . " (with PO files)");
        $this->generateMessagesPotFile();
        $this->generateLanguagePoFiles();
    }

    private function generateLanguagePoFiles(): void
    {
        foreach ($this->locales as $lang) {
            $poFile = $this->modulePath . "/locale/$lang.po";
            if (!is_file($poFile)) {
                GettextCli::log("Creating $poFile");
                $cmd = "msginit --no-translator --input=" . escapeshellarg($this->potFile) . " --locale=" . escapeshellarg($lang) . " --output=" . escapeshellarg($poFile);
            } else {
                GettextCli::log("Updating $poFile");
                $cmd = "msgmerge --previous --update " . escapeshellarg($poFile) . " " . escapeshellarg($this->potFile);
            }
            $this->exec("$cmd 2>&1", "Creating $poFile from $this->potFile (msgmerge)");
        }
    }

    protected function generateMessagesPotFile(): void
    {
        // PHP
        $this->extractInBatches(['*.php'], $this->potFile, "--add-location -L PHP");

        // JavaScript
        // Add __() and _n() to the list of functions to extract besides ngettext() and gettext()
        $this->extractInBatches(['*.js'], $this->potFile, "--add-location -L JavaScript --keyword=__ --keyword=_n:1,2");

        // HTML
        $files = $this->findFiles(['*.html']);
        $tmpFile = tempnam(sys_get_temp_dir(), 'gettext-php-strings.tmp') or throw new \RuntimeException("Cannot create temporary file");
        file_put_contents($tmpFile, '<?php' . "\n");
        foreach ($files as $file) {
            file_put_contents($tmpFile, $this->extractHtmlStrings($file), FILE_APPEND);
        }
        $cmd = $this->getExtractCmd([$tmpFile], $this->potFile, '-L PHP --no-location');
        $this->exec("$cmd 2>&1", "Extracting gettext strings from HTML files...");
    }

    /**
     * Extract translate-able strings from the HTML files by splitting them into 100-file batches.
     *
     * @param array<string> $filePatterns
     * @param string $potFile
     * @param string $extraParams
     * @return void
     */
    protected function extractInBatches(array $filePatterns, string $potFile, string $extraParams): void
    {
        $files = $this->findFiles($filePatterns);
        foreach (array_chunk($files, 100) as $filesChunk) {
            $cmd = $this->getExtractCmd($filesChunk, $potFile, $extraParams);
            $this->exec("$cmd 2>&1", "Extracting gettext strings from " . count($filesChunk) . " ". implode(", ", $filePatterns). " files...");
            // echo $cmd."\n";
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
            "--omit-header " . // prevennnt the #, fuzzy header in .pot file
            // "--add-comments " . // --add-comments=TAG place comment blocks starting with TAG and preceding keyword lines in output file
            "--join-existing --from-code UTF-8 -F --package-version=\"1.0\" " .
            "-o " . escapeshellarg($potFile) . " " .
            "--package-name=\"{$this->moduleName}\" " .
            ($extraParam ?? '') . " " .
            implode(" ", $filesEsc);

        // Run it with bash
        $cmd = "bash -c " . escapeshellarg($cmd);

        return $cmd;
    }

    /**
     * Extract translate-able strings from the HTML file.
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
        if (!in_array($this->getGettextMode($file, ''), ['translate'])) {
            return '';
        }
        GettextCli::log("📄 Preparing extraction from $file");

        $relativeFile = str_replace($this->modulePath, '', $file);
        $strings = [];
        $doc = $this->loadHtmlFile($file);

        // Element   
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
