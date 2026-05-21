<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Zolinga\Cms\Events\ContentElementEvent;
use Zolinga\System\Events\ListenerInterface;
use Locale;
use DOMElement;

/**
 * Handler for <language-selector/> content tag.
 *
 * Outputs a custom element with language data and a hidden language list
 * that the front-end JS uses for the popup.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-05-15
 */
class LanguageSelectorListener implements ListenerInterface
{
    public function handle(ContentElementEvent $event): void
    {
        global $api;

        /** @var LocaleService $localeService */
        $localeService = $api->locale;
        $locale = $localeService->locale;
        $urls = $localeService->getLocalizedUrls();

        $doc = $event->output->ownerDocument;

        $el = $this->createElement($doc, 'language-selector', [
            'render' => 'client',
            'data-curr-locale' => $localeService->jsLocale,
            'data-curr-name' => $localeService->supportedLangNames[$locale] ?? $locale,
            'data-curr-name-en' => Locale::getDisplayLanguage($locale, 'en_US'),
        ]);
        $event->output->appendChild($el);

        $box = $this->createElement($doc, 'div', [
            'class' => 'language-popup',
            'popover' => 'auto',
            'hidden' => 'true',
        ]);
        $el->appendChild($box);

        $list = $this->createElement($doc, 'div', [
            'class' => 'language-list',
        ]);
        $box->appendChild($list);

        foreach ($localeService->supportedLangs as $tag => $lang) {
            $langName = $localeService->supportedLangNames[$tag];
            $langNameEn = Locale::getDisplayLanguage($tag, 'en_US');
            $jsLocale = str_replace('_', '-', $localeService->supportedLocales[$tag]);
            $isCurrent = $tag === $locale;

            $item = $this->createElement($doc, 'a', [
                'class' => 'language' . ($isCurrent ? ' current' : ''),
                'href' => $urls[$tag],
                'data-locale' => $jsLocale,
                'data-lang' => $lang,
                'data-name' => $langName,
                'data-name-en' => $langNameEn,
            ]);

            $span = $doc->createElement('span');
            $span->textContent = $langName;
            $item->appendChild($span);
            $list->appendChild($item);
        }

        $event->setStatus($event::STATUS_OK, 'Language selector rendered');
    }

    /**
     * Create a DOM element with attributes.
     *
     * @param \DOMDocument $doc
     * @param string $name Tag name
     * @param array<string, string> $attrs Attribute name => value
     * @return DOMElement
     */
    private function createElement(\DOMDocument $doc, string $name, array $attrs = []): DOMElement
    {
        $el = $doc->createElement($name);
        foreach ($attrs as $key => $value) {
            $el->setAttribute($key, $value);
        }
        return $el;
    }
}
