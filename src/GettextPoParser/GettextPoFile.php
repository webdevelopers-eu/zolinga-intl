<?php

declare(strict_types=1);

namespace Zolinga\Intl\GettextPoParser;

/**
 * Parse, inspect, and update a gettext PO/POT file.
 *
 * Only msgstr values and flags are mutated; everything else round-trips
 * unchanged. Output is compatible with msgmerge/msgfmt.
 *
 * Usage:
 *   $po = GettextPoFile::load('cs_CZ.po');
 *
 *   // Find untranslated entries
 *   foreach ($po->getUntranslatedEntries() as $entry) {
 *       echo $entry->msgid . "\n";
 *       foreach ($entry->translatorComments as $c) {
 *           echo "  $c\n"; // "#. TRANSLATORS: ..." hints
 *       }
 *   }
 *
 *   // Update translations (key = msgid, or "context\x04msgid")
 *   $po->translate([
 *       'Hello' => 'Ahoj',
 *       'apple' => ['jablko', 'jablka', 'jablek'],
 *       "menu\x04Open" => 'Otevřít',
 *   ]);
 *
 *   $po->save('cs_CZ.po');
 */
class GettextPoFile
{
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /** Ordered translation entries. */
    public private(set) array $entries = [];

    /** Header fields (Project-Id-Version, Language, Plural-Forms, ...). */
    public private(set) array $header = [];

    public ?string $lang {
        get => $this->locale ? \Locale::getPrimaryLanguage($this->locale) : null;
    }

    /**
     * Locale code e.g. "en_US" or "cs_CZ"
     */
    public ?string $locale {
        get {
            global $api;
            if (empty($this->header['Language'])) {
                return null;
            }
            $locale = trim(\Locale::getPrimaryLanguage($this->header['Language']) . '_' . \Locale::getRegion($this->header['Language']), '_');
            if (strlen($locale) === 2) {
                $locale = array_find($api->config['intl']['locales'], fn($l) => str_starts_with($l, $locale . '_'));
                if (!$locale) {
                    throw new \RuntimeException("Locale '{$this->header['Language']}' from PO header is not supported");
                }
                return \Locale::canonicalize($locale); 
            }
            return $locale;
        }
    }

    /** Number of plural forms from Plural-Forms header, null if unknown. */
    public private(set) ?int $nplurals = null;

    /** Plural expression from Plural-Forms header, null if unknown. Example: "(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2" */
    public private(set) ?string $plural = null;

    /**
     * Examples of n values for each plural form, generated from the plural expression. Max 3 examples per form. 
     * 
     * Example: [0 => [1, 21, 31], 1 => [0, 2, 3], 2 => [5, 6, 7]]
     * Eg. for given language plural form 0 is used when n=1,21,31 , form 1 is used when n=0,2,3 , and form 2 is used when n=5,6,7.
     */
    public private(set) ?array $pluralCountExamples {
        get {
            if (!$this->pluralCountExamples) {
                $this->pluralCountExamples = $this->generatePluralCountExamples();
            }
            return $this->pluralCountExamples;
        }
    }

    public function __construct()
    {
    }
    /**
     * Encode a string for PO output.
     *
     * Converts JSON unicode escapes (\uXXXX) for control chars to PO hex
     * escapes (\xNN), and \f to \x0c (msgcat rejects \f).
     */
    public static function poEncode(string $s): string
    {
        $json = json_encode($s, self::JSON_FLAGS);
        return preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})|\\\\f/',
            function ($m) {
                if ($m[0] === '\\f') {
                    return '\\x0c';
                }
                $cp = hexdec($m[1]);
                return $cp < 0x20 ? sprintf('\\x%02x', $cp) : $m[0];
            },
            $json
        );
    }

    /**
     * Decode a PO quoted string fragment.
     *
     * Converts PO \xNN hex escapes and C escapes (\a, \v, \0, \f) to
     * JSON-compatible \uNNNN, then decodes via json_decode.
     */
    public static function poDecode(string $escaped): string
    {
        // \$ is not a valid JSON escape but appears in some PO files
        $escaped = str_replace('\\$', '$', $escaped);
        // Convert PO \xNN hex escapes to JSON \uNNNN
        $escaped = preg_replace_callback(
            '/\\\\x([0-9a-fA-F]{2})/',
            fn($m) => sprintf('\\u%04x', hexdec($m[1])),
            $escaped
        );
        // Convert C escapes not valid in JSON
        $escaped = str_replace(
            ['\\a', '\\v', '\\0', '\\f'],
            ['\\u0007', '\\u000b', '\\u0000', '\\u000c'],
            $escaped
        );
        // Escape literal control characters (0x00-0x1F) that json_decode rejects
        $escaped = preg_replace_callback(
            '/[\x00-\x1F]/',
            fn($m) => sprintf('\\u%04x', ord($m[0])),
            $escaped
        );
        return json_decode('"' . $escaped . '"', flags: JSON_THROW_ON_ERROR);
    }
    
    public static function load(string $path): self
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read: $path");
        }
        $self = new self();
        $self->parse($content);
        return $self;
    }

    /** Entries with empty msgstr. */
    public function getUntranslatedEntries(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn($e) => trim($e->msgid) !== '' && (!$e->isTranslated || $e->isFuzzy)
        ));
    }

    /**
     * Parse a single PO entry string into a GettextPoEntry.
     *
     * Reuses the full parser by cloning this file's header, appending the
     * entry string, parsing it, and returning the non-header entry.
     *
     * Example:
     *   $entry = $po->parseToEntry('#. Note' . "\n" . 'msgid "Hello"' . "\n" . 'msgstr "Ahoj"');
     *   echo $entry->msgid;  // "Hello"
     *   echo $entry->msgstr['']; // "Ahoj"
     */
    public function parseToEntry(string $entryString): GettextPoEntry
    {
        // Clone this file's header so the entry inherits the same Plural-Forms etc.
        $tmp = clone $this;
        $tmp->entries = [];
        $tmp->parse($tmp->toString() . "\n" . $entryString . "\n");

        // Find the non-header entry (msgid !== '')
        foreach ($tmp->entries as $entry) {
            if ($entry->msgid !== '') {
                return $entry;
            }
        }

        throw new \RuntimeException("Failed to parse entry string: no non-header entry found: " . json_encode($entryString));
    }

    /**
     * Split "context\x04msgid" msgids into proper msgctxt + msgid pairs.
     *
     * xgettext encodes context as "context\x04message" inside msgid.
     * This method finds those entries and moves the context part into
     * $entry->context, leaving only the message in $entry->msgid.
     *
     * Returns true if any entries were changed.
     */
    public function fixContext(): bool
    {
        $changed = false;

        foreach ($this->entries as $entry) {
            $sepPos = strpos($entry->msgid, "\x04");
            if ($sepPos === false) {
                continue;
            }

            $entry->context = substr($entry->msgid, 0, $sepPos);
            $entry->msgid = substr($entry->msgid, $sepPos + 1);

            // Also strip context from msgidPlural if present
            if ($entry->msgidPlural !== null) {
                $pluralSepPos = strpos($entry->msgidPlural, "\x04");
                if ($pluralSepPos !== false) {
                    $entry->msgidPlural = substr($entry->msgidPlural, $pluralSepPos + 1);
                }
            }

            $changed = true;
        }

        return $changed;
    }

    /**
     * Find entries matching a msgid or a custom filter callback.
     *
     * Usage:
     *   $entries = $po->find('Hello');                    // by msgid
     *   $entries = $po->find('Open', 'menu');             // by msgid + context
     *   $entries = $po->find(fn($e) => $e->isFuzzy);        // by callback
     *
     * @return array<GettextPoEntry>
     */
    public function find(string|callable $msgid, ?string $context = null): array
    {
        if (is_callable($msgid)) {
            return array_values(array_filter($this->entries, $msgid));
        }

        return array_values(array_filter(
            $this->entries,
            fn($e) => $e->msgid === $msgid && ($context === null || $e->context === $context)
        ));
    }

    /**
     * Bulk-translate entries by msgid (and optionally context).
     *
     * Iterates the $translations map and for each key looks up matching
     * entries via find(). The key is either a plain msgid, or
     * "context\x04msgid" when context is needed.
     *
     * On match, calls $entry->translate() which sets msgstr and strips the
     * fuzzy flag. Keys with no matching entries are silently ignored.
     *
     * Example:
     *   $po->translate([
     *       'Hello' => 'Ahoj',                          // singular, no context
     *       'apple' => ['jablko', 'jablka', 'jablek'],  // plural
     *       "menu\x04Open" => 'Otevřít',                 // with context
     *   ]);
     *
     * @param array<string, string|array<string,string>> $translations
     *   Key = msgid, or "context\x04msgid" when context exists.
     *   Value = string for singular, or ['0' => '...', '1' => '...'] for plurals.
     */
    public function translate(array $translations): void
    {
        foreach ($translations as $key => $value) {
            $parts = explode("\x04", $key, 2);
            $msgid = $parts[1] ?? $parts[0];
            $context = isset($parts[1]) ? $parts[0] : null;

            foreach ($this->find($msgid, $context) as $entry) {
                $entry->translate($value);
            }
        }
    }

    /** Serialize to PO format string. */
    public function toString(): string
    {
        $out = [];

        if ($this->header) {
            $out[] = 'msgid ""';
            $out[] = 'msgstr ""';
            foreach ($this->header as $k => $v) {
                $out[] = json_encode("$k: $v\n", self::JSON_FLAGS);
            }
            $out[] = '';
        }

        foreach ($this->entries as $entry) {
            if ($entry->msgid === '') continue; // we already output the header
            $out[] = $entry->toPoString();
            $out[] = '';
        }

        return implode("\n", $out);
    }

    public function save(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $this->toString());
    }

    // -- parser ---------------------------------------------------------------

    public function parse(string $content): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $this->entries = [];
        $this->header = [];
        $this->nplurals = null;
        $this->plural = null;
        $this->pluralCountExamples = null;

        $i = 0;
        $n = count($lines);

        // Entries
        while ($i < $n) {
            if (trim($lines[$i]) === '') { $i++; continue; }
            $entry = $this->parseEntry($lines, $i);
  
            if ($entry instanceof GettextPoEntry) {
                if ($entry->msgid === '') {
                    $this->parseHeaderEntry($entry);
                }
                $this->entries[] = $entry;
            }
        }
    }

    private function parseHeaderEntry(GettextPoEntry $entry): void 
    {
        if (preg_match_all('/^([^:]+):\s*(.*)$/m', $entry->msgstr[''], $hm, PREG_SET_ORDER)) {
            foreach ($hm as $h) {
                $this->header[trim($h[1])] = trim($h[2]);
            }
        }
        if (isset($this->header['Plural-Forms'])) {
            $this->parsePluralForms($this->header['Plural-Forms']);
        }
    }

    /**
     * Parse the Plural-Forms header and set properties like $this->nplurals. 
     * Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;"
     */
    private function parsePluralForms(string $pluralForms): void
    {
        global $api;
        foreach (explode(';', trim($pluralForms, "\r\n ;")) as $part) {
            $part = trim($part);
            list($key, $value) = explode('=', $part, 2) + [null, null];
            $key = trim($key ?? '');
            $value = trim($value ?? '');

            switch (trim($key)) {
                case 'nplurals':
                    $this->nplurals = (int) trim($value);
                    break;
                case 'plural':
                    $this->plural = $value;
                    break;
                default:
                    $api->log->info("i18n", "Unknown Plural-Forms part: " . json_encode($part));
            }
        }
    } 

    /**
     * Generate examples of plural counts based on the Plural-Forms header.
     * 
     * For each plural form, finds up to 3 example n values that trigger that form according to the plural expression.
     * 
     * Returns an array mapping plural form index to example n values, e.g. [0 => [1, 21, 31], 1 => [0, 2, 3], 2 => [5, 6, 7]].
     * Eg. for given language plural form 0 is used when n=1,21,31 , form 1 is used when n=0,2,3 , and form 2 is used when n=5,6,7.
     *  
     * @return array|null
     */
    private function generatePluralCountExamples(int $count = 3): ?array
    {
        if (!$this->nplurals || !$this->plural) {
            return null;
        }

        $examples = array_fill(0, $this->nplurals, []);
        $remaining = range(0, $this->nplurals - 1);

        $formula = preg_replace('/[^n0-9><=!&|?:()]+/', '', $this->plural);
        // That is ugly, but still the simplest
        $cmdTemplate = sprintf('env -i /bin/sh -c "echo $(( %s ))"', trim(escapeshellarg(str_replace('n', '%n', $formula)), "'\""));

        for ($n = 0; $n < 200; $n++) {
            $cmd = str_replace('%n', strval($n), $cmdTemplate);
            $form = intval(shell_exec($cmd));
            if (in_array($form, $remaining)) {
                $examples[$form][] = $n;
                if (count($examples[$form]) >= $count) {
                    $remaining = array_diff($remaining, [$form]);
                }
                if (empty($remaining)) {
                        break;
                }
            }
        }

        return $examples;
    }

    /**
     * Parse one entry. Advances $i past it.
     *
     * @param array<string> $lines
     */
    private function parseEntry(array $lines, int &$i): ?GettextPoEntry
    {
        $n = count($lines);
        $comments = [];
        $context = null;
        $msgid = null;
        $msgidPlural = null;
        $msgstr = [];

        // Collect comment lines
        while ($i < $n && ($lines[$i] ?? null) && $lines[$i][0] === '#') {
            $comments[] = $lines[$i++];
        }

        // Read keywords until next comment, empty line, or EOF
        while ($i < $n) {
            $line = $lines[$i];
            if ($line === '' || $line[0] === '#') break;

            if (!preg_match(
                '/^(?<keyword>msgctxt|msgid_plural|msgid|msgstr)(?:\[(?<plural>\d+)\])?\s/',
                $line,
                $m
            )) {
                $i++;
                continue;
            }

            switch ($m['keyword']) {
                case 'msgctxt':
                    $context = $this->readString($lines, $i);
                    break;
                case 'msgid':
                    $msgid = $this->readString($lines, $i);
                    break;
                case 'msgid_plural':
                    $msgidPlural = $this->readString($lines, $i);
                    break;
                case 'msgstr':
                    $msgstr[$m['plural'] ?? ''] = $this->readString($lines, $i);
                    break;
            }
        }

        if ($msgid === null) return null;

        // Store context as pseudo-comment for round-trip fidelity
        if ($context !== null) {
            $comments[] = 'msgctxt ' . json_encode($context, self::JSON_FLAGS);
        }

        return new GettextPoEntry($comments, $msgid, $msgidPlural, $msgstr, $this->nplurals);
    }

    /**
     * Read a quoted value from $lines[$i], joining continuation lines.
     * Advances $i past all consumed lines.
     *
     * @param array<string> $lines
     */
    private function readString(array $lines, int &$i): string
    {
        $s = '';
        // First line: keyword "..."  → decode the quoted part
        if (preg_match('/"(.*)"\s*$/', $lines[$i], $m)) {
            $s = self::poDecode($m[1]);
        }
        $i++;
        // Continuation lines: "..."
        while ($i < count($lines) && preg_match('/^"(.*)"\s*$/', $lines[$i], $m)) {
            $s .= self::poDecode($m[1]);
            $i++;
        }
        return $s;
    }
}

