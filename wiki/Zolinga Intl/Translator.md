# Translator Service

The `$api->translator` service provides AI-powered translation between languages supported by the system.

## Synchronous Translation

```php
$translated = $api->translator->translate(
    string: 'Hello world',
    fromLang: 'en_US',
    toLang: 'cs_CZ',
    context: 'This is a greeting on a public website.',
    ai: 'translator', // optional, default: 'translator'
);
echo $translated; // "Ahoj světe"
```

## Asynchronous Translation

For longer texts or batch processing, use `translateAsync()`. It queues the translation and dispatches your callback event when done.

```php
use Zolinga\Intl\Events\TranslateEvent;
use Zolinga\System\Types\OriginEnum;

// Prepare your callback event and set request parameters
// Response parameters are arbitrary - you can store any metadata you need for your callback
// system will set $event->response['data'] to the translated text when done
$event = new TranslateEvent(
    'my-unique-translation-id', // required — duplicate UUIDs are silently ignored
    'my:translation:done',
    OriginEnum::INTERNAL,
    [
        'string' => 'Hello world',
        'fromLang' => 'en_US', // optional: default en_US
        'toLang' => 'cs_CZ',
        'context' => 'Website greeting', // optional
        'priority' => 0.8, // optional, float between 0 and 1, higher = processed sooner
    ],
    [
        'recordId' => 42,  // custom metadata preserved for your callback
        'field' => 'title',
    ],
);

$uuid = $api->translator->translateAsync($event);
// $uuid is the AiEvent UUID queued for processing
```

Your callback listener receives the `TranslateEvent` with `$event->response['data']` set to the translated text, plus all your custom response keys intact.

```php
// In your listener:
function onTranslationDone(TranslateEvent $event): void {
    $translated = $event->response['data'];
    $recordId = $event->response['recordId']; // 42
    $field = $event->response['field'];       // 'title'
    // Update your record...
}
```

## How It Works

1. `translate()` builds a prompt from the template, calls `$api->ai->prompt()`, returns the result.
2. `translateAsync()` builds the prompt, wraps the `TranslateEvent` inside an `AiEvent` queued via `$api->ai->promptAsync()`.
3. When the AI finishes, `AiGenerator` dispatches `ai:translation`. The `TranslatorService` listens for it, restores the original `TranslateEvent`, sets `response['data']`, and dispatches it to your callback.

## Prompt Template

The default template is at `module://zolinga-intl/data/translate-prompt.txt`. Override it by creating `config://zolinga-intl/translate-prompt.txt`.

Template variables:

| Variable | Description |
|---|---|
| `\{{SOURCE_LANG}}` | Display name of source language (e.g. "English") |
| `\{{SOURCE_CODE}}` | ISO code of source language (e.g. "en") |
| `\{{TARGET_LANG}}` | Display name of target language |
| `\{{TARGET_CODE}}` | ISO code of target language |
| `\{{CONTEXT}}` | Optional context string (prefixed with "Context: ") |
| `\{{TEXT}}` | The text to translate |

## AI Backend

The `translator` backend is defined in `zolinga.json` config. Default model: `translagemma:12b`. Override in your `config/local.json`:

```json
{
    "ai": {
        "backends": {
            "translator": {
                "model": "your-preferred-model",
                "url": "http://your-ollama:3000/api"
            }
        }
    }
}
```

## Processing Async Translations

Run the background worker:

```bash
bin/zolinga ai:generate --loop
```
