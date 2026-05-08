---
name: zolinga-intl-module-i18n
description: Use when asked to localize a module, add translations, or work with gettext/dgettext/ngettext/dngettext in PHP, JS, or HTML. Covers marking strings, extracting, editing .po files, compiling, and the special 'default' domain.
argument-hint: "<module-name or locale>"
---

# Quick Reference

| Task | Command |
|------|---------|
| Extract strings from all domains | `bin/zolinga gettext:extract` |
| Extract strings from one domain | `bin/zolinga gettext:extract --domains=<domains>` |
| Compile translations for all domains | `bin/zolinga gettext:compile` |
| Compile translations for one domain | `bin/zolinga gettext:compile --domains=<domains>` |
# Pipeline Overview

```
Mark strings in code → Extract → Translate .po → Compile → Verify
```

## Step 1: Configure Locales

Edit `config/global.json`:

```json
{"intl": {"locales": ["en_US", "cs-CZ", "de-DE"]}}
```

The first locale is the default. The `zolinga-intl` module's `zolinga.json` also has a default `config.intl.locales` of `["en_US"]`.

# Marking Strings for Translation

## PHP

Use `dgettext()` for single strings and `dngettext()` for plural forms. The domain is always the module folder name.

```php
echo dgettext('my-module', 'Hello, world!');
echo sprintf(dngettext('my-module', 'One apple', '%d apples', 3), 3);
```

For context-aware translations, append `GETTEXT_CTX_END` (`"\x04"`) before the message:

```php
echo dgettext('my-module', 'Confirm form submission' . GETTEXT_CTX_END . 'Send');
```

## JavaScript

Import the domain-bound helper from the module's dist path, then use `gettext()` / `ngettext()`:

```javascript
import {gettext, ngettext} from "/dist/my-module/locale/gettext.js?my-module";

console.log(gettext("Hello, world!"));
console.log(ngettext("One apple", "%s apples", 3, 3));
```

## HTML (Static Translation)

Add `<meta name="gettext" content="translate"/>` in `<head>`, then mark elements with the `gettext` attribute:

```html
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="gettext" content="translate"/>
    <title gettext=".">My Page</title>
  </head>
  <body>
    <h1 gettext=".">Hello, World!</h1>
    <img alt="Company logo" gettext="alt" src="logo.png"/>
  </body>
</html>
```

The `gettext` attribute is a whitespace-separated list of keywords:
- `.` — translate the element's text content
- `attrName` — translate the value of that attribute

# Translation Workflow

1. **Mark** translatable strings in PHP, JS, and HTML.
2. **Extract** with `bin/zolinga gettext:extract [--domains=<domains>]`.  
   This generates `.pot` templates and `.po` files.
3. **Translate** the `.po` files (see "Where to Edit .po Files" below).
4. **Compile** with `bin/zolinga gettext:compile [--domains=<domains>]`.  
   This produces `.mo` binaries, `.json` JS dictionaries, and translated `.html` files.

# Where to Edit .po Files

## Module Domains

For a module named `my-module`, the only files a human should edit are:

```
modules/my-module/locale/
  en_US.po
  cs_CZ.po
  ...
```

Never edit files under `templates/`, `LC_MESSAGES/`, or `install/dist/locale/` manually — they are generated.

## The `default` Domain

The system provides a hard-coded `default` domain for translations that do not belong to a specific module (e.g. project-wide CMS pages, shared templates, or strings in `data/` and `public/`).

- **Server output** (`.po` / `.mo` files): `data/zolinga-intl/default/locale/`
- **Client output** (JS `.json` dictionaries): `public/data/zolinga-intl/default/locale/`

Edit these `.po` files for project-wide strings:

```
data/zolinga-intl/default/locale/
  en_US.po
  cs_CZ.po
  ...
```

> **Warning:** A module cannot be named `default`; it conflicts with this built-in domain and throws `GettextDomainException`.

# File Output Structure (After Extraction / Compilation)

## Per-Module Domain (`my-module`)

```
modules/my-module/
  locale/                              ← serverOutput — human-editable .po files live here
    templates/
      server.pot                       ← PHP strings
      client.pot                       ← JS strings
      static.pot                       ← HTML strings
    messages.pot                       ← merged (msgcat of the three above)
    en_US.po                           ← from messages.pot (msginit/msgmerge)
    cs_CZ.po
    en_US/LC_MESSAGES/
      my-module.mo                     ← runtime PHP dictionary
      my-module.static.mo              ← runtime HTML dictionary
    cs_CZ/LC_MESSAGES/
      my-module.mo
      my-module.static.mo
  install/dist/locale/                 ← clientJsonOutput — JS dictionaries
    en-US.json
    cs-CZ.json
```

## `default` Domain

```
data/zolinga-intl/default/locale/         ← serverOutput
  templates/
    server.pot
    client.pot
    static.pot
  messages.pot
  en_US.po                               ← human-editable
  cs_CZ.po
  en_US/LC_MESSAGES/
    default.mo
    default.static.mo
  cs_CZ/LC_MESSAGES/
    default.mo
    default.static.mo

public/data/zolinga-intl/default/locale/  ← clientJsonOutput
  en-US.json
  cs-CZ.json
```

# How It Works

- **Extractor** scans PHP, JS, and HTML separately, producing `server.pot`, `client.pot`, and `static.pot`. It then merges them into `messages.pot` and creates/updates per-language `.po` files.
- **Compiler** intersects each `{lang}.po` with `server.pot` to produce `{domain}.mo` for PHP, and with `static.pot` to produce `{domain}.static.mo` for HTML translation. It also intersects `{lang}.po` with `client.pot` to generate JS `.json` dictionaries.
- **HTML translation** uses `dgettext("{domain}.static", ...)` so static strings do not pollute the runtime `.mo` domain.
- **LocaleService::initGettext()** binds all module domains plus the `default` domain at request startup. An optional `.static` suffix is bound only during CLI compilation for HTML pre-translation.

## Troubleshooting

| Problem | Cause | Fix |
|---------|-------|-----|
| PHP returns English string | `.mo` file missing or stale | Run `gettext:compile`; restart PHP |
| JS returns English string | `.json` dictionary missing or 404 | Check `{MODULE}/install/dist/locale/{lang}.json` exists; run `gettext:compile` |
| "fuzzy translations" error on compile | `.po` file has `#, fuzzy` markers | Edit `.po` file, remove `fuzzy` keyword from correct entries |
| Locale not supported by OS | OS locale not generated | Run `locale -a` to check; generate locale on OS level |
| `bindtextdomain` warning | `locale/` directory missing in module | Create `locale/` dir or skip if module has no translations |
| Cherry-pick file not updating new strings | New strings need plain `gettext` attr (no hash) | Add English text with `gettext="."` attr; compiler will translate and add hash |
| JS catalog 404 | Module not installed/symlinked to `public/dist/` | Run module install or check symlink |
| `xgettext` not found | Missing gettext tools | Install: `apt install gettext` |

## References

- `modules/zolinga-intl/wiki/Zolinga Intl.md` — primary i18n documentation
- `modules/zolinga-intl/wiki/ref/event/gettext/extract.md` — CLI extract event docs
- `modules/zolinga-intl/wiki/ref/event/gettext/compile.md` — CLI compile event docs
- `modules/zolinga-intl/src/GettextCli.php` — CLI handler for extract/compile
- `modules/zolinga-intl/src/Gettext/Extractor.php` — string extraction logic
- `modules/zolinga-intl/src/Gettext/Compiler.php` — PO→MO + HTML compilation
- `modules/zolinga-intl/src/Gettext/JavascriptCompiler.php` — PO→JSON compilation
- `modules/zolinga-intl/src/Gettext/JavascriptExtractor.php` — JS-specific extraction

# Rules of Thumb

- Always use the module folder name as the gettext domain in PHP (`dgettext('my-module', ...)`).
- Only ever edit `*.po` files in `modules/{name}/locale/` or `data/zolinga-intl/default/locale/`.
- Run `extract` after adding or changing translatable strings.
- Run `compile` after editing `.po` files.
- Use `GETTEXT_CTX_END` when the same English word needs different translations in different contexts.
- For HTML static translation, never manually edit the generated `*.{lang}-{TERRITORY}.html` files unless you remove or change the `<meta name="gettext" content="replace"/>` tag. 
