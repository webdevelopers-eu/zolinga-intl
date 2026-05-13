<?php

declare(strict_types=1);

namespace Zolinga\Intl\Models;

use DOMElement;

class GettextElement extends DOMElement implements GettextNodeInterface
{
    // We need to save XML and hash it to avoid problem with very same/duplicate elements on the page with 
    // different markup - which $this->textContent cannot distinguish. 
    public ?GettextAttribute $gettextAttribute { get => $this->getAttributeNode('gettext') ?: null; }
    public string $gettextDomain { get => GettextDocument::parseGettextAttr($this->gettextAttribute)['.']['domain'] ?? ''; }
    public string $gettextHash { get => GettextDocument::parseGettextAttr($this->gettextAttribute)['.']['hash'] ?? ''; }
    public string $gettextString { get => $this->normalizeSpace($this->getGettextString()); }
    public array $descendantElements { get => iterator_to_array($this->getElementsByTagName('*')); }
    /** @disregard */
    public string $gettextContext { get => trim($this->ownerDocument?->xpath->evaluate('string((ancestor-or-self::*[@gettext-context])[1]/@gettext-context)', $this)); }
    /** @disregard */
    public int $gettextContextAdjacent { get => (int)($this->ownerDocument?->xpath->evaluate('number((ancestor-or-self::*/@gettext-context-adjacent)[1])', $this) ?: 0); }
    public private(set) bool $isTranslated = false;
    public bool $isTranslatable {
        get => (bool) preg_match('/(^|\s)(.+:)?\.(#|\s|$)/', $this->gettextAttribute->textContent ?? '');
    }

    public function ensureGettextHash(): bool
    {        
        if ($this->gettextHash) {
            return false; // hash already exists, no need to update
        }

        $newHash = GettextDocument::generateRandomHash();
        GettextDocument::updateGettextAttrHash($this->gettextAttribute, '.', $newHash);

        return true; // hash was updated
    }

    /**
     * Get the gettext string for this element, which is the concatenation of its text content and the gettext attributes of its descendant elements.
     * 
     * @return string The gettext string for this element.
     */
    private function getGettextString(int $idx = 0, ?GettextElement $rootElement = null): string
    {
        $isRoot = $idx === 0;
        $string = '';
        foreach (($rootElement ?? $this)->childNodes as $child) {
            if ($child instanceof GettextElement) {
                $idx++;
                $string .= "<$idx>" . $this->getGettextString($idx, $child) . "</$idx>";
            } elseif ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $string .= $child->textContent;
            }
        }

        // Normalize spaces and newlines

        return ($isRoot && $this->gettextContext ? $this->gettextContext . GETTEXT_CTX_END : '') . trim($string);
    }

    private function normalizeSpace(string $str): string
    {
        // Replace multiple spaces with a single space, and trim leading/trailing spaces
        return preg_replace('/\s+/u', ' ', trim($str)) ?? '';
    }

    /**
     * Translate element.
     *
     * @param string $translation the new translation string
     * @param array<GettextElement>|null $elements the array of elements to be used instead of $translation placeholders <1>, <2>, etc. 
     * @return void
     */
    public function translate(string $translation, ?array $elements = null): void {
        $this->translateNode($translation, $elements, 0);
    }


    private function translateNode(string $translation, ?array $elements, int $depth): void {
        global $api;

        if (!$depth) { // only for root
            $gettextTagList = GettextDocument::parseGettextAttr($this->gettextAttribute);
            if (!$gettextTagList['.']['hash']) {
                GettextDocument::updateGettextAttrHash($this->gettextAttribute, '.', $this->gettextHash);
            }
        }

        $this->isTranslated = true;

        if (!$elements) {
            $elements = $this->descendantElements;
        }
        // Now we need to parse the string '<1>..<2>...</2>...</1>' and find the corresponding 
        // descendant elements to replace their content with the corresponding parts in the translation string.
        $output = $this->ownerDocument->createDocumentFragment();

        $pattern = '/\G(?<before>.*?)<(?<idx>\d+)>(?<subtranslation>.*?)<\/\k<idx>>/su';
        $pos = 0;
        while (preg_match($pattern, $translation, $matches, PREG_OFFSET_CAPTURE, $pos)) {
            $pos = $matches[0][1] + strlen($matches[0][0]); // indexes are in bytes
            $idx = (int)$matches['idx'][0];
            $beforeText = $matches['before'][0];
            $subtranslation = $matches['subtranslation'][0];

            // Before text
            $output->append($beforeText);

            // Subelement
            $templateElement = $elements[$idx - 1] ?? null;
            if ($templateElement) {
                $subelement = $this->ownerDocument->adoptNode($templateElement->cloneNode(true));
                $output->append($subelement);
                /** @var GettextElement $subelement */
                $subelement->translateNode($subtranslation, $elements, $depth + 1);
            } else {
                $api->log->warning('i18n', "No template element found for index <$idx> in translation: $translation");
                $output->append($subtranslation);
            }
        }

        // Final text after the last tag
        $output->append(substr($translation, $pos));

        // Replace the content of the element with the new translation
        $this->textContent = '';
        $this->appendChild($output);
    }
}
