<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use DOMDocument;
use Locale;
use Zolinga\Intl\GettextCli;

/**
 * Compile language po files and translate html files to the supported locales.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class JavascriptCompiler extends GettextAbstract
{

    public function __construct(string $modulePath)
    {
        parent::__construct($modulePath);
    }

    /**
     * Generate MO files from translated PO files and translate HTML files if
     * they have <meta name="gettext" content="translate"> or <meta name="gettext" content="cherry-pick">
     *
     * @return void
     */
    public function compile(): void
    {
        $this->makePoFiles();
    }

    /**
     * Merge the translated .po files from $this->modulePath/locale/$lang.po with the .pot file $this->modulePath 
     * into the $this->modulePath/install/dist/locale/$lang.po
     */
    private function makePoFiles(): void
    {
        foreach ($this->locales as $lang) {
            $jsLang = Locale::getPrimaryLanguage($lang) . "-" . Locale::getRegion($lang);
            $poFile = $this->modulePath . "/locale/$lang.po";
            $potFile = $this->modulePath . "/install/dist/locale/messages.pot";
            $jsonFile = $this->modulePath . "/install/dist/locale/$jsLang.json";

            // Merge the .pot file with the .po file;
            $output = $this->exec(
                "msgmerge --no-fuzzy-matching " . escapeshellarg($poFile) . " " . escapeshellarg($potFile) . " 2> /dev/null",
                "Merging $poFile with $potFile (msgmerge)"
            ) or throw new \RuntimeException("Cannot merge $poFile with $potFile");

            // Convert the .po file to .json
            $json = $this->convertToJson($output);
            file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * The input is the output of the msgmerge --stringtable-output command
     *  
     * Generate the .json file from the .po file
     * {
     *     "": {
     *       language: "fr",
     *       "plural-forms": "nplurals=2; plural=n>1;",
     *     },
     *     Welcome: "Bienvenue",
     *     "There is %1 apple": ["Il y a %1 pomme", "Il y a %1 pommes"],
     *   }
     *  
     * @param string $input
     * @return array<string, mixed>
     */
    private function convertToJson(string $input): array
    {
        $lines = explode("\n", trim($input) . "\n");
        $blockList = [];
        $block = ["type" => null, "value" => ''];
        while (count($lines)) {
            $line = trim(array_shift($lines));
            if (preg_match('/^(msgid|msgid_plural|msgstr(\[\d+\])?)\s+\"/', $line)) {
                if ($block["type"]) {
                    $blockList[] = $block;
                    $block = ["type" => null, "value" => ''];
                }
                // echo "KEY: $line\n";
                list($key, $value) = explode(' ', $line, 2);
                $block["type"] = trim($key);
                $block["value"] = json_decode(trim($value), true, 1, JSON_THROW_ON_ERROR);
            } elseif (substr($line, 0, 1) === '"') {
                // echo "VALUE: $line\n";
                $block["value"] .= json_decode($line, true, 1, JSON_THROW_ON_ERROR);
            } elseif ($block['type']) {
                // echo "END: $line\n";
                $blockList[] = $block;
                $block = ["type" => null, "value" => ''];
            }
        }

        $list = [];
        $key = null;
        $values = [];
        foreach ($blockList as $block) {
            if ($block['type'] == 'msgid') {
                if (count($values)) {
                    $list[$key] = count($values) == 1 ? $values[0] : $values;
                    $values = [];
                }
                $key = $block['value'] . "";
            } elseif ($block['type'] == 'msgid_plural') {
                // $key = $block['value'];
            } elseif (substr($block['type'], 0, 6) == 'msgstr') {
                $values[] = $block['value'];
            }
        }
        if (count($values)) {
            $list[$key] = count($values) == 1 ? $values[0] : $values;
        }

        // print_r($blockList);
        // print_r($list);

        if (isset($list[''])) {
            $list[''] = $this->parseAsHeaders($list['']);
        }

        return $list;
    }

    /**
     * Parse string as HTTP-like headers and return associative array.
     *
     * @param string $str
     * @return array<string, string>
     */
    private function parseAsHeaders(string $str): array
    {
        $headers = [];
        foreach (explode("\n", $str) as $header) {
            $header = trim($header);
            if (!$header) continue;
            list($name, $value) = explode(':', $header, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }
        return $headers;
    }
}
