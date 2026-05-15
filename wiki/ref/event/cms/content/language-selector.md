# Language Selector

The `<language-selector/>` tag renders an inline language switcher. It displays the current language name and on click shows a popup with all supported languages.

## Usage

```html
<language-selector/>
```

## How It Works

1. **Server-side (PHP)**: The `LanguageSelectorListener` handler processes the tag and outputs a `<language-selector>` custom element with:
   - `data-curr-locale` — current locale in JS format (e.g. `cs-CZ`)
   - `data-curr-name` — localized current language name (e.g. `čeština`)
   - `data-curr-name-en` — English name of current language (e.g. `Czech`)
   - Hidden `.language-list` div with all supported languages and their links

2. **Client-side (JS)**: The `LanguageSelector` web component:
   - Displays a globe icon via CSS `::before { content: "🌐︎ " }`
   - Displays the current language name via CSS `::after { content: attr(data-curr-name) }`
   - On click, creates a popover with all language options using the Popover API
   - Language items are `<a>` elements with `href` — no JS navigation needed

## Supported Languages

Languages are taken from `$api->config['intl']['locales']`. Each language gets:
- Localized name via `Locale::getDisplayLanguage()`
- English name via `Locale::getDisplayLanguage($tag, 'en_US')`
- A link to the current page in that language

## Styling

The component uses CSS anchor positioning (`anchor-name`, `position-anchor`, `position-area`) for the popup. Default styles are in `language-selector.css`.

Override by targeting:
- `language-selector` — the inline trigger
- `language-selector::before` — globe icon
- `language-selector::after` — current language name
- `.language-popup` — the popover container
- `.language-popup .language` — individual language links (`<a>` elements)
- `.language-popup .language.current` — the currently active language
