# Gettext Workflow

Overview of the `gettext:extract` and `gettext:compile` pipelines in the Zolinga Intl module.

Both commands are triggered via CLI:

```bash
bin/zolinga gettext:extract [--domains=DOMAIN_NAMES]
bin/zolinga gettext:compile [--domains=DOMAIN_NAMES]
```

`--domains` accepts a comma-separated list of domain names (e.g. `--domains=mod1,mod2`). Omitting `--domains` processes all gettext domains (module domains plus the built-in `default` domain).

Handler: `Zolinga\Intl\GettextCli` (`modules/zolinga-intl/src/GettextCli.php`)

---

## gettext:extract

Two phases: (A) PHP/JS/HTML extraction for server-side gettext, (B) JS extraction for client-side dictionaries.

### Phase A — Server-side extraction (`Extractor`)

Source: `modules/zolinga-intl/src/Gettext/Extractor.php`

Target directory: `{MODULE}/locale/`

#### Step 1 — Generate `messages.pot`

Three source types are merged into one `.pot` file.

**1a. PHP files (`*.php`)**

Processed in batches of 100 files.

```
xgettext --verbose --omit-header --join-existing --from-code UTF-8 -F \
  --package-version="1.0" -o {potFile} --package-name="{moduleName}" \
  --add-location -L PHP \
  <(sed 's/^[[:space:]]*//' file1.php) <(sed 's/^[[:space:]]*//' file2.php) ...
```

- `--join-existing` — merges into existing `.pot` (non-destructive)
- `--omit-header` — prevents `#, fuzzy` header in `.pot`
- `sed` wrapper — strips indentation from HEREDOC strings (xgettext limitation)
- Wrapped in `bash -c` to support process substitution `<(…)`

**1b. JavaScript files (`*.js`)**

Same as PHP but with JavaScript language and custom keywords:

```
xgettext ... --add-location -L JavaScript --keyword=__ --keyword=_n:1,2 \
  <(sed 's/^[[:space:]]*//' file.js) ...
```

- `--keyword=__` — extracts `__("string")` calls
- `--keyword=_n:1,2` — extracts `_n("singular", "plural", n)` calls

**1c. HTML files (`*.html`)**

Only files with `<meta name="gettext" content="translate">` are processed.

Method: `Extractor::extractHtmlStrings()`

1. Load HTML via `DOMDocument`
2. Find all elements with a `gettext` attribute via XPath `//@gettext`
3. Parse the attribute value — space-separated tags in format `[domain:](.|attributeName)[#hash]`
   - `.` means the element's text content
   - `attributeName` means that HTML attribute's value
   - `#hash` entries are skipped (already translated)
4. Generate a temporary PHP file with fake `_()` / `dgettext()` calls:

```php
// {relativeFilePath}: Text content of <element ...>
_("Translatable text content");

// {relativeFilePath}: Attr title of <element ...>
dgettext("other-domain", "Translatable attribute value");
```

5. Run `xgettext` on the temporary file:

```
xgettext ... -L PHP --no-location -o {potFile} {tmpFile}
```

#### Step 2 — Generate/update per-locale `.po` files

For each locale in `LINGUAS` file (merged with `$api->locale->supportedLocales`):

**New locale (no `.po` file yet):**

```
msginit --no-translator --input={potFile} --locale={lang} --output={lang}.po
```

**Existing locale:**

```
msgmerge --previous --update {lang}.po {potFile}
```

- `--previous` — adds `#| msgid` comments showing previous source text
- `--update` — updates the `.po` in place

### Phase B — Client-side JS extraction (`JavascriptExtractor`)

Source: `modules/zolinga-intl/src/Gettext/JavascriptExtractor.php`

Target directory: `{MODULE}/install/dist/locale/`

Extends `Extractor`, overrides constructor to point at `install/dist` subdirectory. Only generates `messages.pot` — no `.po` files are created. The `.pot` serves as input for the compile phase.

---

## gettext:compile

Two phases: (A) PHP `.mo` compilation + HTML file translation, (B) JS `.json` dictionary generation.

### Phase A — Server-side compilation (`Compiler`)

Source: `modules/zolinga-intl/src/Gettext/Compiler.php`

#### Step 1 — Compile `.po` to `.mo`

For each locale:

```
msgfmt {lang}.po --strict -o {lang}/LC_MESSAGES/{domain}.mo
```

- `--strict` — enables strict validation
- Output directory is created if missing
- Warns if `.po` contains `#, fuzzy` entries

#### Step 2 — Translate HTML files

Only files with `<meta name="gettext" content="translate">` are processed.

Method: `Compiler::translateHtmlFiles()`

1. **Build dictionary** (`buildFileDictionary()`):
   - Load source HTML via `DOMDocument`
   - Find all `gettext` attributes
   - For each translatable string, compute `SHA1` hash (first 6 chars)
   - Add hash to the `gettext` attribute: `title` → `title#abc123`
   - Store mapping: `"domain:#hash" => "original English string"`

2. **Generate translated files** for each supported locale (except `en_US`):
   - Target filename: `page.cs-CZ.html`, `page.de-DE.html`, etc.
   - Two modes based on existing target file's `<meta name="gettext" content="...">`:
     - **`replace`** (default for new files): Clone the source document, translate all strings
     - **`cherry-pick`**: Load existing target file, only translate nodes whose `gettext` attribute lacks a hash (user-modified translations preserved)
   - Translation uses PHP's `dgettext()` at runtime with the compiled `.mo` files
   - Output saved via `DOMDocument::saveHTMLFile()`

### Phase B — Client-side JS compilation (`JavascriptCompiler`)

Source: `modules/zolinga-intl/src/Gettext/JavascriptCompiler.php`

Target: `{MODULE}/install/dist/locale/{lang}-{REGION}.json`

For each locale:

1. **Merge** the module's translated `.po` with the JS `.pot`:

```
msgmerge --no-fuzzy-matching {module}/locale/{lang}.po \
  {module}/install/dist/locale/messages.pot
```

- `--no-fuzzy-matching` — prevents fuzzy matches from polluting the output
- Output is captured (not written to file)

2. **Convert to JSON** (`convertToJson()`):
   - Parses the `msgmerge` output line by line
   - Produces a JSON dictionary:

```json
{
  "": {
    "language": "fr",
    "plural-forms": "nplurals=2; plural=n>1;"
  },
  "Welcome": "Bienvenue",
  "There is %1 apple": ["Il y a %1 pomme", "Il y a %1 pommes"]
}
```

---

## File Processing Summary

| File Type | Extract Action | Compile Action |
|---|---|---|
| `*.php` | `xgettext -L PHP` → `.pot` | `msgfmt` → `.mo` |
| `*.js`, `*.mjs`, `*.ts`, `*.tsx` | `xgettext -L JavaScript --keyword=__ --keyword=_n:1,2` → `.pot` | `msgmerge` + custom parser → `.json` |
| `*.html` (mode: `translate`) | DOM parse → fake PHP → `xgettext -L PHP` → `.pot` | `dgettext()` via `.mo` → translated HTML files |

## Shell Commands Reference 

| Command | Used In | Phase |
|---|---|---|
| `xgettext` | `Extractor::getExtractCmd()` | extract |
| `msginit` | `Extractor::generateLanguagePoFiles()` | extract |
| `msgmerge --previous --update` | `Extractor::generateLanguagePoFiles()` | extract |
| `msgmerge --no-fuzzy-matching` | `JavascriptCompiler::makePoFiles()` | compile |
| `msgfmt --strict` | `Compiler::compileLanguagePoFiles()` | compile |
| `sed 's/^[[:space:]]*//'` | `Extractor::getExtractCmd()` | extract (HEREDOC fix) |
| `bash -c` | `Extractor::getExtractCmd()` | extract (process substitution) |

All commands run via `shell_exec()` after `chdir()` to the module root.
