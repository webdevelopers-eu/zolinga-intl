---
name: system-i18n-translation
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

For writing localizable code (PHP `dgettext`, JS `gettext`, HTML `gettext` attributes, `WebComponentIntl`), see the `system-i18n-coding` skill.

## Pipeline Overview

```
Mark strings in code → Extract → Translate .po → Compile → Verify
```

1. **Mark** translatable strings in code (see `system-i18n-coding` skill).
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
- **HTML**: Open the `.{lang-REGION}.html` file in a browser.

## File Structure Reference

```
📁 my-module/
    📁 locale/                              ← PHP gettext domain root (serverOutput)
        📁 templates/
            📄 server.pot                   ← PHP strings only
            📄 client.pot                   ← JS strings only
            📄 static.pot                   ← HTML strings only
        📄 messages.pot                     ← merged (msgcat of all 3)
        📄 en_US.po                         ← English translations
        📄 cs_CZ.po                         ← Czech translations
        📁 en_US/LC_MESSAGES/
            📄 my-module.mo                  ← PHP runtime (from server.pot)
            📄 my-module.static.mo           ← HTML translation (from static.pot)
        📁 cs_CZ/LC_MESSAGES/
            📄 my-module.mo
            📄 my-module.static.mo
    📁 install/dist/
        📁 locale/                           ← JS gettext domain root (clientJsonOutput)
            📄 en-US.json                    ← compiled JSON (JS)
            📄 cs-CZ.json                    ← compiled JSON (JS)
        📄 my-page.html                      ← source HTML (meta: translate)
        📄 my-page.cs-CZ.html                ← compiled translation
        📁 web-components/my-component/
            📄 my-component.html             ← source template
            📄 my-component.cs-CZ.html       ← localized template
```

### Default Domain

```
📁 data/zolinga-intl/default/locale/         ← serverOutput
    📁 templates/
        📄 server.pot
        📄 client.pot
        📄 static.pot
    📄 messages.pot
    📄 en_US.po
    📄 cs_CZ.po
    📁 en_US/LC_MESSAGES/
        📄 default.mo
        📄 default.static.mo
    📁 cs_CZ/LC_MESSAGES/
        📄 default.mo
        📄 default.static.mo

📁 public/data/zolinga-intl/default/locale/  ← clientJsonOutput
    📄 en-US.json
    📄 cs-CZ.json
```

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
- `modules/zolinga-intl/src/Gettext/Extractor.php` — string extraction logic (PHP, JS, HTML)
- `modules/zolinga-intl/src/Gettext/Compiler.php` — PO→MO + HTML translation
- `modules/zolinga-intl/src/Gettext/JavascriptCompiler.php` — PO→JSON compilation
- `modules/zolinga-intl/src/I18nService.php` — `$api->i18n` domain discovery service
- `modules/zolinga-intl/src/Models/GettextDocument.php` — DOM-based HTML translation model
