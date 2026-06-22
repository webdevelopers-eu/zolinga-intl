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
     * @param string $fromLang Source language tag (e.g. "en_US"). Must be a valid ICU/CLDR locale.
     * @param string $toLang Target language tag (e.g. "cs_CZ"). Must be a valid ICU/CLDR locale.
     * @param string|null $context Optional context to guide the translation.
     * @param string|null $aiCapabilities AI capability (or array of) to use. Default: "translate:<from>-<to>" where each side is the primary language subtag (e.g. "translate:en-cs").
     * @return string The translated text.
     * @throws \InvalidArgumentException If $fromLang or $toLang is not a valid locale tag.
     */
    public function translate(
        string $string,
        string $fromLang,
        string $toLang,
        ?string $context = null,
        string|array|null $aiCapabilities = null,
        GettextTemplateEnum $template = GettextTemplateEnum::DEFAULT
    ): ?string {
        global $api;

        $this->assertLocale($fromLang, 'fromLang');
        $this->assertLocale($toLang, 'toLang');

        $aiCapabilities = $aiCapabilities ?? $this->defaultCapability($fromLang, $toLang);
        $prompt = $this->buildPrompt($string, $fromLang, $toLang, $context, $template);
        $result = $api->ai->prompt($aiCapabilities, $prompt);

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

        $fromLang = $event->request['fromLang']
            or throw new \InvalidArgumentException("translateAsync request is missing 'fromLang'.");
        $toLang = $event->request['toLang']
            or throw new \InvalidArgumentException("translateAsync request is missing 'toLang'.");

        $this->assertLocale($fromLang, 'fromLang');
        $this->assertLocale($toLang, 'toLang');

        $prompt = $this->buildPrompt(
            $event->request['string'],
            $fromLang,
            $toLang,
            $event->request['context'] ?? '',
            $event->request['template'] ?? GettextTemplateEnum::DEFAULT,
        );

        $aiEvent = new AiEvent(
            $event->uuid,
            'i18n:translation:done',
            OriginEnum::INTERNAL,
            [
                'capabilities' => $event->request['capabilities'] ?? $this->defaultCapability($fromLang, $toLang),
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
     * Build the default AI capability for a language pair.
     *
     * Normalizes both tags to their primary language subtag so the matcher
     * sees a short, locale-independent capability like "translate:en-cs"
     * regardless of whether the caller passed "en_US" or "en".
     *
     * @param string $fromLang Source locale tag.
     * @param string $toLang Target locale tag.
     * @return string Capability string in the form "translate:<from>-<to>".
     */
    private function defaultCapability(string $fromLang, string $toLang): string
    {
        $from = Locale::getPrimaryLanguage($fromLang) ?: $fromLang;
        $to = Locale::getPrimaryLanguage($toLang) ?: $toLang;
        return "translate:$from-$to";
    }

    /**
     * Validate that the given value is a well-formed ICU/CLDR locale tag.
     *
     * Rejects empty strings, locale strings containing characters outside the
     * BCP 47 subset (letters, digits, and `-`/`_` separators), and strings
     * that PHP's locale parser cannot parse.
     *
     * @param string $locale The locale tag to validate.
     * @param string $field  The field name (used in the exception message).
     * @throws \InvalidArgumentException If the tag is not a valid locale.
     */
    private function assertLocale(string $locale, string $field): void
    {
        if ($locale === '' || !preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,4})*$/', $locale)) {
            throw new \InvalidArgumentException(
                "TranslatorService: '$field' must be a valid locale tag (e.g. 'en', 'en_US', 'zh-Hans-CN'), got: " . var_export($locale, true)
            );
        }
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

        $prompt = $template;
        $recursion = 8;
        do {
            $prompt = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $prompt,
                $count
            );
        } while ($recursion-- && $count > 0); // $context may have variables too.

        return $prompt;
    }
}
