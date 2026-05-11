<?php

declare(strict_types=1);

namespace Zolinga\Intl;

use Locale;
use Zolinga\AI\Events\AiEvent;
use Zolinga\Intl\Events\TranslateEvent;
use Zolinga\Intl\Types\GettextTemplateEnum;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Events\RequestResponseEvent;
use Zolinga\System\Events\ServiceInterface;
use Zolinga\System\Types\OriginEnum;

/**
 * AI-powered translation service.
 *
 * Provides synchronous and asynchronous translation between languages
 * supported by the system, using the configured AI backend.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-04-30
 */
class TranslatorService implements ServiceInterface, ListenerInterface
{
    /**
     * Translate a string synchronously using AI.
     *
     * @param string $string The text to translate.
     * @param string $fromLang Source language tag (e.g. "en_US").
     * @param string $toLang Target language tag (e.g. "cs_CZ").
     * @param string|null $context Optional context to guide the translation.
     * @param string $ai AI backend name to use. Default: "translator".
     * @return string The translated text.
     */
    public function translate(
        string $string,
        string $fromLang,
        string $toLang,
        ?string $context = null,
        string $ai = 'translator',
        GettextTemplateEnum $template = GettextTemplateEnum::DEFAULT
    ): ?string {
        global $api;

        $prompt = $this->buildPrompt($string, $fromLang, $toLang, $context, $template);
        $result = $api->ai->prompt($ai, $prompt);

        return is_string($result) ? (trim($result) ?: null) : null;
    }

    /**
     * Queue a translation for asynchronous processing via AI.
     *
     * The TranslateEvent is serialized and stored inside an AiEvent.
     * When the AI finishes, the AiEvent is dispatched back to the
     * 'i18n:translation:done' listener, which restores the TranslateEvent,
     * sets the response data, and dispatches it to the original caller.
     *
     * @param TranslateEvent $event The translation request event.
     * @return string The UUID of the queued AiEvent.
     */
    public function translateAsync(TranslateEvent $event): string
    {
        global $api;

        $prompt = $this->buildPrompt(
            $event->request['string'],
            $event->request['fromLang'],
            $event->request['toLang'],
            $event->request['context'] ?? '',
            $event->request['template'] ?? GettextTemplateEnum::DEFAULT,
        );

        $aiEvent = new AiEvent(
            $event->uuid,
            'i18n:translation:done',
            OriginEnum::INTERNAL,
            [
                'ai' => $event->request['ai'] ?? 'translator',
                'prompt' => $prompt,
                'priority' => $event->request['priority'] ?? 0.5,
            ],
            [
                'callbackEvent' => json_encode($event),
            ],
        );

        return $api->ai->promptAsync($aiEvent);
    }

    /**
     * Handle the completed AI translation and dispatch the callback event.
     *
     * Listens on 'i18n:translation:done' — invoked by AiGenerator after the AI
     * finishes processing the queued prompt.
     *
     * @param RequestResponseEvent $aiEvent The completed AiEvent.
     */
    public function onTranslation(RequestResponseEvent $aiEvent): void
    {
        global $api;

        $callbackData = $aiEvent->response['callbackEvent'] ?? null;
        if (!$callbackData || !is_string($callbackData)) {
            $api->log->error('intl', 'i18n:translation:done received without callbackEvent in response.');
            return;
        }

        /** @var TranslateEvent $translateEvent */
        $translateEvent = TranslateEvent::fromArray(json_decode($callbackData, true));
        $translateEvent->response['data'] = $aiEvent->response['data'] ?? '';
        $translateEvent->dispatch();
    }

    /**
     * Build the translation prompt from the template.
     *
     * @param string $string The text to translate.
     * @param string $fromLang Source language tag.
     * @param string $toLang Target language tag.
     * @param string|null $context Optional context.
     * @return string The filled prompt.
     */
    private function buildPrompt(
        string $string,
        string $fromLang,
        string $toLang,
        ?string $context = '',
        GettextTemplateEnum $template = GettextTemplateEnum::DEFAULT
    ): string {
        global $api;

        $templatePath = 'module://zolinga-intl/data/prompt-translate-' . $template->value . '.txt';
        $template = file_get_contents($templatePath)
            or throw new \RuntimeException("Failed to load translation prompt template: $templatePath");
            
        $sourceLang = Locale::getDisplayLanguage($fromLang, $fromLang) ?: $fromLang;
        $sourceCode = Locale::getPrimaryLanguage($fromLang) ?: $fromLang;
        $targetLang = Locale::getDisplayLanguage($toLang, $toLang) ?: $toLang;
        $targetCode = Locale::getPrimaryLanguage($toLang) ?: $toLang;

        // Ensure CONTEXT does not contain two or more consecutive newlines
        $context = $context ? trim($context) : '';
        $context = preg_replace('/\n{2,}/', "\n", $context);

        $replacements = [
            '{{SOURCE_LANG}}' => $sourceLang,
            '{{SOURCE_CODE}}' => $sourceCode,
            '{{TARGET_LANG}}' => $targetLang,
            '{{TARGET_CODE}}' => $targetCode,
            '{{CONTEXT}}' => $context ? 'Context: ' . $context : '',
            '{{TEXT}}' => $string,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template,
        );
    }
}
