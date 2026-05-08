---
name: zolinga-intl-i18n-translation
description: Use when running the gettext translation pipeline in Zolinga — extracting strings, editing .po files, compiling dictionaries (.mo for PHP, .json for JS), generating translated HTML, and troubleshooting translation issues.
argument-hint: "<module-name> [step:extract|translate|compile|verify]"
---

# Zolinga i18n: Translation Pipeline

## Use When

- Running `gettext:extract` or `gettext:compile` CLI commands.
- Editing `.po` translation files.
- Troubleshooting missing translations, fuzzy entries, or locale detection.
- Understanding the full extract → translate → compile pipeline.
- Setting up a new locale/language for a module or the whole project.

For writing localizable code (PHP `dgettext`, JS `gettext`, HTML `gettext` attributes, `WebComponentIntl`), see the `zolinga-intl-module-i18n` skill.

## Pipeline Overview

```
Mark strings in code → Extract → Translate .po → Compile → Verify
```

1. **Mark** translatable strings in code (see `zolinga-intl-module-i18n` skill).
2. **Extract** strings into `.po` files.
3. **Translate** `.po` files.
4. **Compile** `.po` into `.mo` (PHP), `.json` (JS), and translated HTML files.
5. **Verify** translations work at runtime.

## Step 1: Configure Locales

Edit `config/global.json`:

```json
{"intl": {"locales": ["en_US", "cs-CZ", "de-DE"]}}
```

The first locale is the default. The `zolinga-intl` module's `zolinga.json` also has a default `config.intl.locales` of `["en_US"]`.

## Step 2: Extract

```bash
bin/zolinga gettext:extract --domains=my-domain
```

**What it does:**
1. Scans `{MODULE}/src/**/*.php` for `dgettext()`, `dngettext()` calls → writes to `{MODULE}/locale/templates/server.pot`.
2. Scans `{MODULE}/install/dist/**/*.js` for `gettext()`, `ngettext()`, `__()`, `_n()` calls → writes to `{MODULE}/locale/templates/client.pot`.
3. Scans `{MODULE}/**/*.html` for `gettext` attributes (only files with `<meta name="gettext" content="translate"/>`) → writes to `{MODULE}/locale/templates/static.pot`.
4. Merges all three `.pot` files into `{MODULE}/locale/messages.pot` via `msgcat`.
5. Creates or updates `{MODULE}/locale/{LOCALE}.po` for each configured locale using `msginit`/`msgmerge`.
6. Post-processes each `.pot` file to split `"context\x04message"` entries into proper `msgctxt`/`msgid` pairs.

**Without `--domains`**: processes all gettext domains (module domains plus the built-in `default` domain).

**Requirements**: `xgettext`, `msginit`, `msgmerge`, `msgcat` must be available on the system. PHP `gettext` extension must be loaded.

## Step 3: Translate

Edit `my-module/locale/cs_CZ.po` (and other locale files). Use Poedit or any text editor.

**Important rules:**
- Remove `#, fuzzy` markers from correct translations — fuzzy entries cause compile errors.
- Do not create `locale/` folders or `.po`/`.mo` files manually — use `gettext:extract` to create them.
- The `.po` file shows the full key including context prefix (from `GETTEXT_CTX_END`); translators see the context.

## Step 4: Compile

```bash
bin/zolinga gettext:compile --domains=my-domain
```

**Without `--domains`**: processes all gettext domains (module domains plus the built-in `default` domain).

**What it does:**
1. **Server-side (.mo):** Merges `{MODULE}/locale/{LOCALE}.po` with `{MODULE}/locale/templates/server.pot` via `msgmerge` → temporary `.po` → `msgfmt` → `{MODULE}/locale/{LOCALE}/LC_MESSAGES/{MODULE}.mo`.
2. **Static (.static.mo):** Merges `{MODULE}/locale/{LOCALE}.po` with `{MODULE}/locale/templates/static.pot` via `msgmerge` → temporary `.po` → `msgfmt` → `{MODULE}/locale/{LOCALE}/LC_MESSAGES/{MODULE}.static.mo`.
3. Binds `.static` domains via `$api->locale->initGettext('.static')`.
4. Translates HTML files marked with `<meta name="gettext" content="translate"/>` → generates `*.{lang-REGION}.html` files using `GettextDocument` model classes.
5. **Client-side (.json):** Merges `{MODULE}/locale/{LOCALE}.po` with `{MODULE}/locale/templates/client.pot` → temporary `.po` → ad-hoc parser → `{MODULE}/install/dist/locale/{lang-REGION}.json`.

**Warnings**:
- If `.po` files contain `fuzzy` entries, compilation logs an error — review and remove the `fuzzy` keyword from correct translations.
- After compiling, you may need to restart PHP for `.mo` changes to take effect (OPcache).

## Step 5: Verify

- **PHP**: `dgettext('my-module', 'String')` should return the translated string.
- **JS**: Check browser console for `gettext()` output or catalog fetch errors.
---
name: zolinga-intl-i18n-translation
description: Concise translation-pipeline reference. For full details and examples see the `zolinga-intl-module-i18n` skill.
argument-hint: "<module-name> [step:extract|translate|compile|verify]"
---

# Translation Pipeline (Summary)

Use when running the gettext pipeline or troubleshooting translations. For implementation details, examples, and HTML/JS specifics see `zolinga-intl-module-i18n`.

Commands:

```bash
bin/zolinga gettext:extract --domains=my-domain
bin/zolinga gettext:compile --domains=my-domain
```

High-level flow:
- Mark strings in code (PHP/JS/HTML)
- Extract → `.pot` templates → per-locale `.po`
- Translate `.po` files
- Compile → `.mo` / `.json` / translated `.html`
- Verify runtime

Quick checks:
- If PHP shows English: run `bin/zolinga gettext:compile` and restart PHP (OPcache may cache `.mo`).
- If JS shows English: confirm `/dist/{module}/locale/{lang}.json` exists and is served.

For full step-by-step guidance, file layouts, and troubleshooting, see:

- `modules/zolinga-intl/skills/zolinga-intl-module-i18n/SKILL.md`
- `modules/zolinga-intl/wiki/ref/event/gettext/extract.md`
- `modules/zolinga-intl/wiki/ref/event/gettext/compile.md`
