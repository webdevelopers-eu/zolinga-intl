---
name: zolinga-intl-multilingual-support
description: Use when asked to localize a module, add translations, or work with the gettext pipeline. Covers the extract-translate-compile workflow, locale configuration, .po file locations, and troubleshooting.
argument-hint: "<module-name or locale>"
---

# Multilingual Support — Pipeline Overview

The workflow is always: **Mark → Extract → Translate → Compile → Verify**.

## Commands

| Task | Command |
|------|---------|
| Extract strings from all domains | `bin/zolinga gettext:extract --all` |
| Extract strings from selected domains | `bin/zolinga gettext:extract --domains=<domains>[,<domains>]` |
| Compile translations for all domains | `bin/zolinga gettext:compile --all` |
| Compile translations for selected domains | `bin/zolinga gettext:compile --domains=<domains>[,<domains>]` |
**Note**: `gettext:extract` modifies source HTML files in place by adding `#HASH` suffixes to `gettext` attributes. These hashes uniquely identify each translatable element and link source elements to their translations. Commit the updated source files after extraction.
## Configure Locales

Edit `config/global.json`:

```json
{"intl": {"locales": ["en_US", "cs-CZ", "de-DE"]}}
```

The first locale is the default. The `zolinga-intl` module's `zolinga.json` also has a default `config.intl.locales` of `["en_US"]`.

## Where to Edit .po Files

### Rules of .po Editing
- If the primary language is en_US, then do not touch/translate `en_US.po` files. They are the source of truth and should always contain the original English strings without any translations (gettext will return the `msgid` if the `msgstr` is empty, so leaving `en_US.po` untranslated is correct).
- If you are asked to translate .po files, do not use any external tools or programs or Zolinga Translation Service unless specifically instructed. Edit the .po files directly and translate the `msgstr` values while leaving the `msgid` values unchanged. 
- IMPORTANT: Always translate the `.po` files by direct editing in one pass without any batches or use of external tools unless user specifically instructs you to do so. 

### Module Domains

For a module named `my-module`, edit only:

```
modules/my-module/locale/
  en_US.po
  cs_CZ.po
  ...
```

Never edit files under `templates/`, `LC_MESSAGES/`, or `install/dist/locale/` — they are generated.

### The `default` Domain

Project-wide strings (CMS pages, shared templates, `data/` and `public/`) use the `default` domain:

```
data/zolinga-intl/default/locale/
  en_US.po
  cs_CZ.po
  ...
```

> **Warning:** A module cannot be named `default`; it conflicts with this built-in domain.

## How to Mark Strings

See the specialized skills:
## How to Mark Strings

See the specialized skills:
- **PHP**: `zolinga-intl-translations-php`
- **JavaScript**: `zolinga-intl-translations-js`
- **HTML**: `zolinga-intl-translations-html`

## Troubleshooting

| Problem | Cause | Fix |
|---------|-------|-----|
| PHP returns English string | `.mo` file missing or stale | Run `gettext:compile`; restart PHP |
| JS returns English string | `.json` dictionary missing or 404 | Check `{MODULE}/install/dist/locale/{lang}.json` exists; run `gettext:compile` |
| "fuzzy translations" error on compile | `.po` file has `#, fuzzy` markers | Edit `.po` file, remove `fuzzy` keyword from correct entries |
| Locale not supported by OS | OS locale not generated | Run `locale -a` to check; generate locale on OS level |
| `bindtextdomain` warning | `locale/` directory missing in module | Create `locale/` dir or skip if module has no translations |
| JS catalog 404 | Module not installed/symlinked to `public/dist/` | Run module install or check symlink |
| `xgettext` not found | Missing gettext tools | Install: `apt install gettext` |

## References

- `modules/zolinga-intl/wiki/Zolinga Intl.md` — primary i18n documentation
- `modules/zolinga-intl/wiki/ref/event/gettext/extract.md` — CLI extract event docs
- `modules/zolinga-intl/wiki/ref/event/gettext/compile.md` — CLI compile event docs