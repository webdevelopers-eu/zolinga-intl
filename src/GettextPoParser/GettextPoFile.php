<?php

declare(strict_types=1);

namespace Zolinga\Intl\GettextPoParser;

/**
 * Parse, inspect, and update a gettext PO/POT file.
 *
 * Only msgstr values and fuzzy flags are mutated; everything else round-trips
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

    /** Number of plural forms from Plural-Forms header, null if unknown. */
    public private(set) ?int $nplurals = null;

    // -- public API --

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
            fn($e) => !$e->isTranslated
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

        throw new \RuntimeException("Failed to parse entry string: no non-header entry found");
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
     *   $entries = $po->find(fn($e) => $e->fuzzy);        // by callback
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

        $i = 0;
        $n = count($lines);

        // Skip leading blanks
        while ($i < $n && trim($lines[$i]) === '') $i++;

        // Header: msgid "" followed by msgstr "" with continuation lines
        if ($i < $n && $lines[$i] === 'msgid ""') {
            $i++; // skip msgid ""
            $raw = $this->readString($lines, $i);
            if (preg_match_all('/^([^:]+):\s*(.*)$/m', $raw, $hm, PREG_SET_ORDER)) {
                foreach ($hm as $h) {
                    $this->header[trim($h[1])] = trim($h[2]);
                }
            }
            if (isset($this->header['Plural-Forms']) &&
                preg_match('/nplurals=(\d+);/', $this->header['Plural-Forms'], $pm)) {
                $this->nplurals = (int) $pm[1];
            }
            while ($i < $n && trim($lines[$i]) === '') $i++;
        }

        // Entries
        while ($i < $n) {
            if (trim($lines[$i]) === '') { $i++; continue; }
            $entry = $this->parseEntry($lines, $i);
            if ($entry) $this->entries[] = $entry;
        }
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
        $fuzzy = false;

        // Collect comment lines
        while ($i < $n && $lines[$i][0] === '#') {
            $c = $lines[$i];
            if (str_contains($c, 'fuzzy')) $fuzzy = true;
            $comments[] = $c;
            $i++;
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

        return new GettextPoEntry($comments, $msgid, $msgidPlural, $msgstr, $fuzzy, $this->nplurals);
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
            $s = $this->jsonDecodePo($m[1]);
        }
        $i++;
        // Continuation lines: "..."
        while ($i < count($lines) && preg_match('/^"(.*)"\s*$/', $lines[$i], $m)) {
            $s .= $this->jsonDecodePo($m[1]);
            $i++;
        }
        return $s;
    }

    /**
     * Decode a PO quoted string fragment via json_decode.
     *
     * PO uses C-style escapes compatible with JSON except for \$ which
     * some tools emit for literal $ — normalize it before decoding.
     */
    private function jsonDecodePo(string $escaped): string
    {
        // \$ is not a valid JSON escape but appears in some PO files
        $escaped = str_replace('\\$', '$', $escaped);
        // Escape control characters (0x00-0x1F) that json_decode rejects.
        // PO files may contain literal control chars like \x04 (EOT) from
        // gettext context separators.
        $escaped = preg_replace_callback(
            '/[\x00-\x1F]/',
            fn($m) => sprintf('\\u%04x', ord($m[0])),
            $escaped
        );
        return json_decode('"' . $escaped . '"', flags: JSON_THROW_ON_ERROR);
    }
}

