---
name: zolinga-intl-translation-web-components
description: Use when creating or modifying translated Zolinga web components. Covers importing web-component-intl.js, locale-aware template loading, and gettext in component HTML/JS.
argument-hint: "<module-name> <component-tag>"
---

# Web Component Translations

## Use When

- Creating a new web component that needs localized HTML templates or translatable strings.
- Modifying an existing component to support multiple locales.
- Deciding whether a component needs `web-component-intl.js` or the base `web-component.js`.

## Two Things to Localize

A web component has **two separate translation surfaces**:

1. **HTML template** — static strings inside the `.html` file loaded by `loadContent()`.
2. **JavaScript strings** — dynamic strings constructed or emitted from the `.js` file.

## 1. HTML Template — Same Rules as Static HTML

## Module Domain Requirement (MUST)

- The gettext domain for ALL translatable strings in a web-component's HTML **must** be the module name where the component lives. For example, if the component is in the `my-module` module, all `gettext` keywords must be prefixed with `my-module:` — e.g. `gettext="my-module:label"`, `gettext="my-module:."`.
- This applies to both HTML attributes and the `?{domain}` query in JavaScript `gettext.js` imports (see JS section). Using the module name as the domain ensures translations are extracted, stored, and compiled under the correct domain and prevents collisions between modules.


Mark translatable content in the `.html` file exactly as described in **zolinga-intl-translations-html**:

```html
<!DOCTYPE html>
<html>
  <head>
    <meta name="gettext" content="translate"/>
    <link rel="stylesheet" href="my-widget.css"/>
  </head>
  <body>
    <h1 gettext="my-module:.">Hello, World!</h1>
    <button gettext="my-module:." title="Submit form">Send</button>
  </body>
</html>
```

Rules that carry over from static HTML:
- Add `<meta name="gettext" content="translate"/>` in `<head>`.
- Use `gettext="."` for text content, `gettext="alt"` for images, `gettext="title"` for titles, etc.
 - Use `gettext="{module}:."` for text content, `gettext="{module}:alt"` for images, `gettext="{module}:title"` for titles, etc. Replace `{module}` with the actual module name (e.g. `my-module`).
- Use `gettext-context` to disambiguate short strings.
- Warning: Unlike in server-side static HTML files, the `<void gettext=".">` must not be used in web-component static HTMLs as those HTMLs are served as-is without server-side processing.
- Add `<!-- TRANSLATORS: ... -->` comments for context but be aware - those comments will be visible in the final HTML source and may be seen by end users. Do not include sensitive information in those comments.

## 2. JavaScript — Import `web-component-intl.js`

If the component loads an HTML template that may contain translated strings, **always** extend from `WebComponentIntl` instead of the base `WebComponent`:

```javascript
import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';

export default class MyWidget extends WebComponent {
    constructor() {
        super();
        this.ready(this.#init());
    }

    async #init() {
        this.#root = await this.loadContent(
            import.meta.url.replace('.js', '.html'),
            { mode: 'open', allowScripts: true }
        );
    }
}
```
The overloaded `loadContent` method in `web-component-intl.js` automatically rewrites the passed URL to include the locale code when the page locale is not `en-US`, so that the component loads the correct localized template.

### What `web-component-intl.js` Does

It overrides `rewriteURL()` so that `loadContent()` automatically requests a locale-specific template when `document.documentElement.lang` is not `en-US`.

| Locale | `loadContent('my-widget.html')` fetches |
|--------|------------------------------------------|
| `en-US` (default) | `my-widget.html` |
| `cs-CZ` | `my-widget.cs-CZ.html` |
| `de-DE` | `my-widget.de-DE.html` |

The locale code is inserted **just before the file extension**:
- `my-widget.html` → `my-widget.cs-CZ.html`
- `my-widget.css` → `my-widget.cs-CZ.css`

### When You Do NOT Need `web-component-intl.js`

Use the base `/dist/system/js/web-component.js` when:
- The component has **no HTML template** (pure JS component).
- The component's HTML template contains **no translatable strings** (only icons, charts, or dynamic data).
- The component is **never localized** (internal admin tools, debug components).

### JS Strings — Use `gettext.js`

For dynamic strings inside the JavaScript file, import the domain-bound helper:

```javascript
import { gettext, ngettext } from '/dist/zolinga-intl/gettext.js?my-module';

// Inside a method
this.broadcast('message', {
    type: 'success',
    message: gettext('Settings saved successfully.')
});
```

See **zolinga-intl-translations-js** for full rules on JS translation comments and API usage.

## File Layout for a Localized Component

```
modules/my-module/install/dist/web-components/my-widget/
  my-widget.js          # imports web-component-intl.js
  my-widget.html        # base template with gettext attributes
  my-widget.css         # base styles
  my-widget.md          # component documentation
  my-widget.cs-CZ.html  # translated template (generated by gettext:compile)
  my-widget.de-DE.html  # translated template (generated by gettext:compile)
```

The translated `.html` files are **generated automatically** by `bin/zolinga gettext:compile` — do not create them manually. The compiler reads the `.po` translations and produces locale-specific copies of the source template.

## Registration in `zolinga.json`

No special registration is needed for localization. Declare the component normally:

```json
"webComponents": [
    {
        "tag": "my-widget",
        "description": "A localized widget example.",
        "module": "web-components/my-widget/my-widget.js"
    }
]
```

After changing `zolinga.json`, bump the module version and run `bin/zolinga` (no parameters) to refresh the system cache.

## Workflow

1. **Mark** strings in `.html` with `gettext` attributes and in `.js` with `gettext()` calls.
2. **Extract** strings: `bin/zolinga gettext:extract --domains=my-module`

  Important: the `--domains` argument must be the module name (for example `--domains=my-module`) so extracted strings are associated with the correct domain.
3. **Translate** `.po` files in `modules/my-module/locale/` or run `bin/zolinga gettext:autotranslate --domains=my-module` to use the Zolinga AI Translation Service.
4. **Compile** translations: `bin/zolinga gettext:compile --domains=my-module`
5. **Verify** — switch the page locale and confirm the component loads the localized template.

## Common Mistakes

| Mistake | Fix |
|---------|-----|
| Using `web-component.js` for a component with translatable HTML | Switch to `web-component-intl.js` |
| Forgetting `<meta name="gettext" content="translate"/>` in template | Add it so the extractor recognizes the file |
| Manually creating `my-widget.cs-CZ.html` | Let `gettext:compile` generate it from `.po` |
| Hard-coding English strings in JS without `gettext()` | Import `gettext.js` and wrap strings |
| Using variables inside `gettext()` calls | Use literal strings only; substitute variables at runtime with `sprintf` patterns |

## CRITICAL: Localization Scope — Do NOT Touch Existing Code

When localizing an existing `.js` file, the **only** changes allowed are:

1. **Import swap** — If the component loads a localized `.html` template, change the import from `/dist/system/js/web-component.js` to `/dist/zolinga-intl/js/web-component-intl.js`. The class name stays `WebComponent` — only the path changes.
2. **Add gettext import** — Add `import { gettext, ngettext } from '/dist/zolinga-intl/gettext.js?my-module';` to the imports section.
3. **Wrap user-facing strings** — Replace literal strings with `gettext('...')` or `ngettext('...', '...', n)` calls.

**You must NOT:**
- Remove, reorder, or modify any existing import statements (e.g. `import api from '/dist/system/js/api.js'`).
- Change any existing code structure, logic, or formatting.
- Add or remove blank lines around imports.
- Modify any non-translation-related code.
- Do not add hashes to `gettext` attributes in HTML — hashes are auto-generated.

The goal is minimal diff: only the import path, the gettext import line, and wrapping of translatable strings.

## CRITICAL: Only Import What You Actually Use

- **If you import `gettext` or `ngettext`, you MUST use them** in the file. Never add an unused import.
- **If there are no translatable strings** in the JS file, do not import `gettext`/`ngettext` at all.
- **If the component has no localized `.html` template**, use the base `/dist/system/js/web-component.js`, not `web-component-intl.js`.
- **If the component loads a localized `.html` template** (via `loadContent()`), use `/dist/zolinga-intl/js/web-component-intl.js` so locale-specific templates are fetched automatically.
- **If the component has no HTML template at all** (pure JS), use the base `web-component.js`.

## CRITICAL: Use Only `gettext()` and `ngettext()` — Never Short Aliases

In JavaScript, always use the **full function names**:

| ✅ Correct | ❌ Wrong |
|-----------|---------|
| `gettext('Hello')` | `_('Hello')` |
| `ngettext('1 item', '%d items', n)` | `_n('1 item', '%d items', n)` |
| `gettext('Save')` | `__('Save')` |

The short aliases `_()`, `__()`, `_n()` are **never used** in Zolinga JavaScript. Only the explicit `gettext()` and `ngettext()` imports from `/dist/zolinga-intl/gettext.js?{module}` are valid.

## CRITICAL: Never Add Hashes to `gettext` Attributes in HTML

When adding `gettext="domain:."` attributes to HTML elements, **never include a hash** after the dot. The hash is auto-generated by the extraction tool.

| ✅ Correct | ❌ Wrong |
|-----------|---------|
| `gettext="my-module:."` | `gettext="my-module:.#a3f1b9"` |
| `gettext="default:."` | `gettext="default:.#6bd0c5"` |
| `gettext="."` | `gettext=".#abc123"` |

Always use just `gettext="domain:."` or `gettext="."` for the default domain. The hash is calculated and added automatically during extraction.

## References

- **zolinga-intl-translations-html** — full HTML gettext rules
- **zolinga-intl-translations-js** — full JS gettext rules
- **zolinga-intl-multilingual-support** — extract / translate / compile pipeline
- `modules/zolinga-intl/install/dist/js/web-component-intl.js` — source of `WebComponentIntl`
- `system/skills/system-web-components` — general web component authoring rules
