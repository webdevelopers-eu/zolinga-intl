<?php

declare(strict_types=1);

namespace Zolinga\Intl\GettextPoParser;

/**
 * One translation entry in a gettext PO file.
 */
class GettextPoEntry implements \Stringable
{
    /** True when all msgstr forms are non-empty. */
    public bool $isTranslated {
        get {
            if ($this->msgidPlural !== null) {
                $count = $this->nplurals ?? count($this->msgstr);
                for ($i = 0; $i < $count; $i++) {
                    if (trim($this->msgstr[(string) $i] ?? '') === '') {
                        return false;
                    }
                }
                return true;
            }
            return trim($this->msgstr[''] ?? '') !== '';
        }
    }

    /** True when entry has plural forms. */
    public bool $isPlural {
        get => $this->msgidPlural !== null;
    }

    public bool $isSingular {
        get => !$this->isPlural;
    }

    public bool $isFuzzy {
        get => $this->hasFlag('fuzzy');
        set(bool $value) {
            $value ? $this->addFlag('fuzzy') : $this->removeFlag('fuzzy');
        }
    }

    /** msgctxt value extracted from pseudo-comment, null if none. */
    public ?string $context {
        get {
            foreach ($this->comments as $c) {
                if (str_starts_with($c, 'msgctxt ')) {
                    return json_decode(substr($c, 8), flags: JSON_THROW_ON_ERROR);
                }
            }
            return null;
        }
        set (?string $value) {
            // Remove existing msgctxt pseudo-comment
            $this->comments = array_values(array_filter(
                $this->comments,
                fn($c) => !str_starts_with($c, 'msgctxt ')
            ));

            if ($value !== null) {
                $this->comments[] = 'msgctxt ' . GettextPoFile::poEncode($value);
            }
        }
    }

    /** Comments of type "#." (translator notes). @return array<string> */
    public array $translatorComments {
        get {
            $comments = array_filter($this->comments, fn($c) => str_starts_with($c, '#.') && !preg_match('/^#\.\s*(TRANSLATORS:\s*)?SOURCE:/', $c));
            $comments = array_map(fn($c) => preg_replace('/^#\.\s*(TRANSLATORS:\s*)?/', '', trim($c)), $comments); 
            return array_unique($comments);
        }
    }

    /** Comments of type "#:" (source references). @return array<string> */
    public array $references {
        get => array_values(array_filter($this->comments, fn($c) => str_starts_with($c, '#:')));
    }

    /** Comments of type "#," (flags). @return array<string> */
    public array $flags {
        get {
            $rows = array_filter($this->comments, fn($c) => str_starts_with($c, '#,'));
            $rows = array_values(array_map(fn($c) => trim($c, ' #,'), $rows));
            $flags = preg_split('/\s*,\s*/', implode(',', $rows));
            return array_filter($flags);
        }
    }

    /**
     * @param array<string> $comments All comment lines preceding msgid
     * @param string $msgid The untranslated source string
     * @param string|null $msgidPlural Plural form, null if not plural
     * @param array<string,string> $msgstr Translations: ["" => "singular"] or ["0" => "...", "1" => "..."]
     * @param int|null $nplurals Expected plural form count (from Plural-Forms header)
     */
    public function __construct(
        public array $comments = [],
        public string $msgid = '',
        public ?string $msgidPlural = null,
        public private(set) array $msgstr = ['' => ''],
        public ?int $nplurals = null,
    ) {
        $this->comments = array_unique($comments);
    }

    /**
     * Set translation(s) for this entry. Strips fuzzy flag.
     *
     * Singular: $entry->translate('Ahoj')
     *           $entry->translate(['Ahoj'])
     * Plural:   $entry->translate(['jablko', 'jablka', 'jablek'])
     *           $entry->translate(['0' => 'jablko', '1' => 'jablka', '2' => 'jablek'])
     *
     * @throws \InvalidArgumentException if a string is given for a plural entry, or vice versa
     */
    public function translate(string|array $translation): void
    {
        if ($this->isPlural) {
            if (!is_array($translation)) {
                throw new \InvalidArgumentException(
                    "Plural entry requires an array of translations, got string"
                );
            }
            $normalized = [];
            foreach ($translation as $k => $v) {
                $normalized[(string) $k] = $v;
            }
            ksort($normalized, SORT_NUMERIC);
            $this->msgstr = $normalized;
        } else {
            if (is_array($translation) && count($translation) === 1) {
                $translation = reset($translation);
            }
            if (!is_string($translation)) {
                throw new \InvalidArgumentException(
                    "Singular entry requires a string translation, got " . json_encode($translation)
                );
            }
            $this->msgstr = ['' => $translation];
        }

        $this->isFuzzy = false;
    }

    public function removeFlag(string $flag): void
    {
        if (!$this->hasFlag($flag)) return;

        $this->comments = array_values($this->comments);        
        for ($i = 0; $i < count($this->comments); $i++) {
            $c = $this->comments[$i];
            if (str_starts_with($c, '#,') && preg_match('/\s*,\s*' . preg_quote($flag, '/') . '(\s*$|\s*,\s*)/', $c)) {
                $this->comments[$i] = '#, ' . implode(', ', array_filter(
                    $this->parseFlagComment($c),
                    fn($f) => $f !== $flag
                ));

                if (trim($this->comments[$i]) === '#,') {
                    array_splice($this->comments, $i, 1);
                    $i--;
                }
                
                // Remove following #| comments
                $ri = $i + 1;
                while (isset($this->comments[$ri]) && str_starts_with($this->comments[$ri], '#|')) {
                    array_splice($this->comments, $ri, 1);
                }
            }
        }
    }

    public function addFlag(string $flag) {
        if ($this->hasFlag($flag)) return;
        foreach ($this->comments as $i => $c) {
            if (str_starts_with($c, '#,')) {
                $flags = $this->parseFlagComment($c);
                $flags[] = $flag;
                $flags = array_unique($flags);
                $this->comments[$i] = '#, ' . implode(', ', $flags);
                return;
            }
        }
        // No existing flags, add new comment
        $this->comments[] = '#, ' . $flag;
    }

    public function hasFlag(string $flag): bool
    {
        return in_array($flag, $this->flags);
    }

    private function parseFlagComment(string $comment): array
    {
        if (!str_starts_with($comment, '#,')) {
            return [];
        }
        $flags = preg_split('/\s*,\s*/', trim(substr($comment, 2)));
        return array_filter($flags);
    }
    
    /** Render back to PO format. */
    public function toPoString(): string
    {
        $out = [];

        foreach (array_unique($this->comments) as $c) {
            if (str_starts_with($c, 'msgctxt ')) continue;
            $out[] = $c;
        }

        if ($this->context !== null) {
            $out[] = 'msgctxt ' . GettextPoFile::poEncode($this->context);
        }

        $out[] = 'msgid ' . GettextPoFile::poEncode($this->msgid);

        if ($this->isPlural) {
            $out[] = 'msgid_plural ' . GettextPoFile::poEncode($this->msgidPlural);
            ksort($this->msgstr);
            foreach ($this->msgstr as $i => $t) {
                if ($i !== '') {
                    $out[] = 'msgstr[' . $i . '] ' . GettextPoFile::poEncode($t);
                }
            }
        } else {
            $out[] = 'msgstr ' . GettextPoFile::poEncode($this->msgstr[''] ?? '');
        }

        return implode("\n", $out);
    }

    public function __toString(): string
    {
        return $this->toPoString();
    }
}


