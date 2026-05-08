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
| Extract strings from all domains | `bin/zolinga gettext:extract` |
| Extract strings from one domain | `bin/zolinga gettext:extract --domains=<domains>` |
| Compile translations for all domains | `bin/zolinga gettext:compile` |
| Compile translations for one domain | `bin/zolinga gettext:compile --domains=<domains>` |

## Configure Locales

Edit `config/global.json`:

```json
{"intl": {"locales": ["en_US", "cs-CZ", "de-DE"]}}
```

The first locale is the default. The `zolinga-intl` module's `zolinga.json` also has a default `config.intl.locales` of `["en_US"]`.

## Where to Edit .po Files

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
- **PHP**: `zolinga-intl-php-translations`
- **JavaScript**: `zolinga-intl-js-translations`
- **HTML**: `zolinga-intl-html-translations`

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