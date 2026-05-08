---
name: zolinga-intl-js-translations
description: Use when writing JavaScript code that needs localized strings. Covers the gettext.js import, domain binding via query string, and the gettext/ngettext API.
argument-hint: "<module-name>"
---

# JavaScript Translations

## Import and Use

Import the domain-bound helper from the module's dist path. The `?domain` query string sets the gettext domain:

```javascript
import {gettext, ngettext} from "/dist/zolinga-intl/gettext.js?my-module";

console.log(gettext("Hello, world!"));
console.log(ngettext("One apple", "%s apples", 3, 3));
```

Aliases: `gettext` = `__`, `ngettext` = `_n`.

```javascript
import {__, _n} from "/dist/zolinga-intl/gettext.js?my-module";

console.log(__("Hello, world!"));
console.log(_n("One apple", "%s apples", 3, 3));
```

## How It Works

1. `gettext.js` reads the `lang` cookie (set by the locale service).
2. Fetches `/dist/{module}/locale/{lang}.json` (e.g., `/dist/my-module/locale/cs-CZ.json`).
3. If the locale is `en-US`, skips fetch (source strings are English).
4. Initializes the gettext library with the fetched dictionary.

## After Marking Strings

Run `bin/zolinga gettext:extract --domains=my-module` to generate `.po` files, then translate and compile.

See also: **zolinga-intl-multilingual-support** for the full pipeline.