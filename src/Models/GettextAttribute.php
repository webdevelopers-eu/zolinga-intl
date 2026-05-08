<?php

declare(strict_types=1);

namespace Zolinga\Intl\Models;

use DOMAttr;

class GettextAttribute extends DOMAttr implements GettextNodeInterface
{
    public ?GettextAttribute $gettextAttribute { get => $this->ownerElement->getAttributeNode('gettext') ?: null; }
    public string $gettextDomain { get => GettextDocument::parseGettextAttr($this->gettextAttribute)[$this->localName]['domain'] ?? ''; }
    public string $gettextHash { get => GettextDocument::parseGettextAttr($this->gettextAttribute)[$this->localName]['hash'] ?? ''; }
    public string $gettextString { get => ($this->gettextContext ? $this->gettextContext . GETTEXT_CTX_END : '') . trim($this->textContent); }    
    public array $descendantElements { get => []; }
    /** @disregard */
    public string $gettextContext { get => trim($this->ownerDocument?->xpath->evaluate('string((ancestor-or-self::*[@gettext-context])[1]/@gettext-context)', $this)); }
    public private(set) bool $isTranslated = false;

    public function ensureGettextHash(): bool
    {
        if ($this->gettextHash) {
            return false; // hash already exists, no need to update
        }

        $newHash = GettextDocument::calculateHash(microtime(true) . rand(0, 1000000));
        GettextDocument::updateGettextAttrHash($this->gettextAttribute, $this->localName, $newHash);

        return true;
    }

    public function translate(string $translation, ?array $elements = null): void {
        $gettextTagList = GettextDocument::parseGettextAttr($this->gettextAttribute);
        if (!$gettextTagList[$this->localName]['hash']) {
            GettextDocument::updateGettextAttrHash($this->gettextAttribute, $this->localName, $this->gettextHash);
        }

        $this->isTranslated = true;
        $this->textContent = $translation;
    }

}