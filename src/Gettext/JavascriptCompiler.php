<?php

declare(strict_types=1);

namespace Zolinga\Intl\Gettext;

use Locale;

/**
 * Compile client-side gettext: merge .po with client.pot → temporary .po → JSON.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-03-16
 */
class JavascriptCompiler extends GettextAbstract
{
    /**
     * Generate JSON dictionaries from translated PO files.
     *
     * @return void
     */
    public function compile(): void
    {
        $this->makePoFiles();
    }

    /**
     * Merge the translated .po files with client.pot and convert to JSON.
     */
    private function makePoFiles(): void
    {
        foreach ($this->domain->localesWithPO as $lang) {
            $jsLang = Locale::getPrimaryLanguage($lang) . "-" . Locale::getRegion($lang);
            $poFile = $this->domain->serverOutput . "/$lang.po";
            $potFile = $this->domain->clientPotFile;
            $jsonFile = $this->domain->clientJsonOutput . "/$jsLang.json";

            if (!is_dir($this->domain->clientJsonOutput)) {
                mkdir($this->domain->clientJsonOutput, 0777, true) or throw new \RuntimeException("Cannot create directory: " . $this->domain->clientJsonOutput);
            }

            $tmpPo = tempnam(sys_get_temp_dir(), "{$this->domain->name}.$lang.client.") . '.po';
            $output = $this->exec(
                "msgmerge --no-fuzzy-matching " . escapeshellarg($poFile) . " " . escapeshellarg($potFile) . " -o " . escapeshellarg($tmpPo) . " 2>&1",
                "Merging $poFile with $potFile (msgmerge)"
            ) or throw new \RuntimeException("Cannot merge $poFile with $potFile");

            $json = $this->convertToJson(file_get_contents($tmpPo) ?: '');
            file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            unlink($tmpPo);
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
                list($key, $value) = explode(' ', $line, 2);
                $block["type"] = trim($key);
                $block["value"] = json_decode(trim($value), true, 1, JSON_THROW_ON_ERROR);
            } elseif (substr($line, 0, 1) === '"') {
                $block["value"] .= json_decode($line, true, 1, JSON_THROW_ON_ERROR);
            } elseif ($block['type']) {
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
            } elseif (substr($block['type'] ?? '', 0, 6) == 'msgstr') {
                $values[] = $block['value'];
            }
        }
        if (count($values)) {
            $list[$key] = count($values) == 1 ? $values[0] : $values;
        }

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
