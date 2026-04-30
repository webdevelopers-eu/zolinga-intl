---
name: zolinga-intl-translator
description: Use when working with the AI-powered translator service ($api->translator) for synchronous or asynchronous translation between system-supported languages.
argument-hint: "<translate|translateAsync> <fromLang> <toLang>"
---

# Zolinga Intl Translator

## Use When

- Translating text between system-supported languages via AI.
- Setting up async translation pipelines with callback events.
- Configuring or overriding the translator AI backend.
- Customizing the translation prompt template.

## Workflow

### Synchronous Translation

```php
$translated = $api->translator->translate(
    string: 'Hello world',
    fromLang: 'en_US',
    toLang: 'cs_CZ',
    context: 'Website greeting',
);
```

### Asynchronous Translation

```php
use Zolinga\Intl\Events\TranslateEvent;

$event = new TranslateEvent(
    'my-unique-translation-id', // required — duplicate UUIDs are silently ignored
    'my:callback',
    request: [
        'string' => 'Hello world',
        'fromLang' => 'en_US',
        'toLang' => 'cs_CZ',
        'priority' => 0.8,
    ],
    response: [
        'recordId' => 42,
    ],
);

$api->translator->translateAsync($event);
```

Your callback listener receives the `TranslateEvent` with `$event->response['data']` set.

### Customizing the Prompt

Create `config://zolinga-intl/translate-prompt.txt` to override the default template. Available variables: `{{SOURCE_LANG}}`, `{{SOURCE_CODE}}`, `{{TARGET_LANG}}`, `{{TARGET_CODE}}`, `{{CONTEXT}}`, `{{TEXT}}`.

### Backend Configuration

Override in `config/local.json`:

```json
{
    "ai": {
        "backends": {
            "translator": {
                "model": "your-model",
                "url": "http://your-host:3000/api"
            }
        }
    }
}
```

### Processing Async Jobs

```bash
bin/zolinga ai:generate --loop
```

## Key Classes

- `Zolinga\Intl\TranslatorService` — service registered as `$api->translator`
- `Zolinga\Intl\Events\TranslateEvent` — event for async translation requests
- Listens on `ai:translation` to complete the async round-trip

## References

- `modules/zolinga-intl/wiki/Zolinga Intl/Translator.md`
- `modules/zolinga-intl/src/TranslatorService.php`
- `modules/zolinga-intl/src/Events/TranslateEvent.php`
- `modules/zolinga-intl/data/translate-prompt.txt`
