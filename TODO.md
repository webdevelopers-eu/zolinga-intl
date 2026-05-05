# TODO-final: zolinga-intl Refactoring Plan

REVIEW INSTRUCTIONS:
All changes concern only the `zolinga-intl` module. No changes to `system/zolinga.json` or any other module.
When reviewing use all the skills for programming, desing and such that are available to you.

---

## 1. New Types & Classes

### 1.1 `FileTypes` — file type constants

**File:** `src/Types/FileTypes.php`

```php
class FileTypes {
    const PHP        = 1 << 0;  // 1
    const JAVASCRIPT = 1 << 1;  // 2
    const HTML       = 1 << 2;  // 4
    const ALL        = self::PHP | self::JAVASCRIPT | self::HTML;  // 7

    /**
     * Return glob patterns for the given file type bitmask.
     *
     * @param int $fileTypes bitmask of FileTypes constants
     * @return array<string>
     */
    public static function getGlobs(int $fileTypes): array {
        $globs = [];
        if ($fileTypes & self::PHP)        $globs[] = '*.php';
        if ($fileTypes & self::JAVASCRIPT)  $globs = array_merge($globs, ['*.js', '*.mjs']);
        if ($fileTypes & self::HTML)       $globs[] = '*.html';
        return $globs;
    }
}
```

Replaces all hardcoded `'*.php'`, `'*.js'`, `'*.html'` globs throughout the module. Provides default glob patterns:

| Constant | Glob(s) |
|----------|---------|
| `PHP` | `*.php` |
| `JAVASCRIPT` | `*.js`, `*.mjs` |
| `HTML` | `*.html` |

### 1.2 `GettextDomain` — domain descriptor class

**File:** `src/Gettext/GettextDomain.php`

```php
class GettextDomain {
    public function __construct(
        public readonly string $name,                    // e.g. 'acme-module', 'default', 'system'
        public readonly string $serverOutput,            // path for .pot/.po/.mo files (PHP + HTML)
        public readonly string $clientJsonFile,          // path for JS .json dictionaries
        public readonly array $folders = [],             // absolute paths to scan
        public readonly int $fileTypes = FileTypes::ALL,
    ) {}

    public string $serverPotFile { get => $this->serverOutput . '/templates/server.pot'; }
    public string $clientPotFile { get => $this->serverOutput . '/templates/client.pot'; }
    public string $staticPotFile { get => $this->serverOutput . '/templates/static.pot'; }
    public string $messagesPotFile { get => $this->serverOutput . '/messages.pot'; }
}
```

**Default paths per domain type:**

| Domain | `serverOutput` | `clientJsonFile` |
|--------|---------------|-------------------|
| Module (e.g. `acme-module`) | `./modules/acme-module/locale/` | `./modules/acme-module/install/dist/locale/` |
| `default` | `./data/zolinga-intl/default/locale/` | `./public/data/zolinga-intl/default/locale/` |
| `system` | `./system/locale/` | `./system/install/dist/locale/` |

### 1.3 `GettextDomainException` — exception class

**File:** `src/Exceptions/GettextDomainException.php`

```php
namespace Zolinga\Intl\Exceptions;

class GettextDomainException extends \RuntimeException {}
```

Thrown by `LocaleService::initGettext()` when a domain name is registered twice (e.g. a module named `default` conflicts with the hard-coded `default` domain).

### 1.4 `PoEntry` and `PoParser` — .po file reader/writer

**Status:** Deferred to a future AI auto-translate workflow. Not needed for this refactoring.

The current ad-hoc `convertToJson()` / `parseAsHeaders()` in `JavascriptCompiler` (~80 lines) is sufficient. When AI auto-translate is implemented, add reusable `PoEntry`/`PoParser` classes at that time.

---

## 2. New Service: `$api->i18n`

### 2.1 Service class

**File:** `src/I18nService.php`

```php
class I18nService implements ServiceInterface {
    /** @return array<string, GettextDomain> keyed by domain name */
    public function getGettextDomains(): array;
}
```

### 2.2 `getGettextDomains()` logic

1. Build `GettextDomain` objects for all modules that have a `locale/` directory:
   - `name` = module folder name
   - `serverOutput` = `$api->fs->toPath("module://{name}/locale/")`
   - `clientJsonOutput` = `$api->fs->toPath("module://{name}/install/dist/locale/")`
   - `folders` = `[$api->fs->toPath("module://{name}/")]`
   - `fileTypes` = `FileTypes::ALL`

2. Build the hard-coded `default` domain:
   - `name` = `'default'`
   - `serverOutput` = `$api->fs->toPath('private://zolinga-intl/default/locale/')`
   - `clientJsonOutput` = `$api->fs->toPath('public://data/zolinga-intl/default/locale/')`
   - `folders` = `[$api->fs->toPath('private://'), $api->fs->toPath('public://')]`
   - `fileTypes` = `FileTypes::ALL`

3. Return all domains keyed by name.

### 2.3 `zolinga.json` update

Add to `modules/zolinga-intl/zolinga.json` (also bump version to `"2.0"`):

```json
{
    "version": "2.0",
    "listen": [
        {
            "service": "i18n",
            "class": "Zolinga\\Intl\\I18nService",
            "description": "Provides gettext domain discovery and i18n workflow services."
        }
    ]
}
```

No config keys needed — the `default` domain is hard-coded in `I18nService::getGettextDomains()`.

---

## 3. Refactored Gettext Classes

### 3.1 `GettextAbstract` — accept `GettextDomain` instead of module path

**New constructor:**

```php
public function __construct(GettextDomain $domain) {
    global $api;
    $this->domain = $domain;
    $this->checkRequirements();
    $this->locales = array_unique([
        ...$api->locale->supportedLocales,
        'en_US',
        ...$this->findExistingPoLocales($domain->serverOutput)
    ]);
}

/**
 * Find locales that already have .po files in the server output directory.
 *
 * @param string $serverOutput
 * @return array<string>
 */
private function findExistingPoLocales(string $serverOutput): array {
    $locales = [];
    foreach (glob($serverOutput . '/*.po') ?: [] as $file) {
        $locales[] = basename($file, '.po');
    }
    return $locales;
}
```

**Changes:**
- Constructor accepts `GettextDomain $domain` instead of `string $modulePath`
- `$modulePath` removed; `$moduleName` removed; `$domain` property becomes the `GettextDomain` object
- `$potFile` property removed — each method constructs its own path from `$this->domain->serverOutput`
- `findFiles()` scans all `$domain->folders` (not just one `$modulePath`), ignores dot-files and backup extensions defined in `GettextAbstract::IGNORED_EXTENSIONS` (`*~`, `*.bak`, `*.orig`, `*.swp`)
- `findFiles()` accepts a `FileTypes` bitmask int to filter by file type, using the globs from `FileTypes`
- `findFiles()` deduplicates results with `array_unique()` to prevent duplicate entries when folders overlap
- `checkRequirements()` validates all folders in `$domain->folders` and `$domain->serverOutput`; also verifies `msgcat` is available (needed by `mergePotFiles()`) alongside `msginit`, `msgmerge`, `msgfmt`
- `exec()` changes `chdir($this->modulePath)` → `chdir(ROOT_DIR)` — since all folder paths are absolute and under `ROOT_DIR`, xgettext `#:` references become project-root-relative consistently across all folders in the domain
- **LINGUAS removed** — the `LINGUAS` file is no longer created or used. The list of locales to process (`$this->locales`) is derived from `$api->locale->supportedLocales` + `en_US` + any locales that already have `.po` files in `{serverOutput}/`.
- All existing HTML/DOM methods (`loadHtmlFile`, `findTranslatables`, `parseGettextAttr`, `getGettextMode`, `calculateHash`) remain **unchanged** — they don't depend on module path

### 3.2 `Extractor` — split extraction by file type into separate .pot files

**New workflow:**

```
extract()
  ├── extractServerPotFile()  → {serverOutput}/templates/server.pot
  ├── extractClientPotFile()  → {serverOutput}/templates/client.pot
  ├── extractStaticPotFile()  → {serverOutput}/templates/static.pot
  ├── mergePotFiles()     → {serverOutput}/messages.pot  (merge of all 3)
  └── generateLanguagePoFiles()  → {serverOutput}/{lang}.po  (from messages.pot)
```

**Key changes:**
- `generateMessagesPotFile()` is split into three methods, each writing to a separate `.pot` file under `{serverOutput}/templates/`
- `extractServerPotFile()`: same `xgettext -L PHP` logic, output to `server.pot`
- `extractClientPotFile()`: same `xgettext -L JavaScript --keyword=__ --keyword=_n:1,2` logic, output to `client.pot`
- `extractStaticPotFile()`: same DOM-parse → fake PHP → `xgettext -L PHP` logic, output to `static.pot`
- New `mergePotFiles()`: runs `msgcat` on all three `templates/*.pot` → `messages.pot`
- `generateLanguagePoFiles()`: unchanged logic (msginit/msgmerge), but reads from `messages.pot`
- Each `extract*PotFile()` method **truncates (unlinks) its target `.pot` file before starting** — `xgettext --join-existing` appends to whatever is on disk, so re-runs would accumulate stale entries without this; truncating first makes every extraction idempotent
- `extractInBatches()` and `getExtractCmd()`: unchanged, just parameterized with target pot file
- `extractHtmlStrings()` and `makePhpLine()`: **unchanged** except `$relativeFile` now uses `$api->fs->toZolingaUri($file)` instead of `$this->modulePath` — consistent with `exec()` now using `chdir(ROOT_DIR)`

### 3.3 `Compiler` — compile .po → .mo, translate HTML using static domain

**New workflow:**

```
compile()
  ├── compileLanguagePoFiles()  → {serverOutput}/{lang}/LC_MESSAGES/{domain}.mo
  │     (msgmerge {lang}.po templates/server.pot → tmp.po, msgfmt tmp.po → {domain}.mo)
  ├── compileStaticPoFiles()   → {serverOutput}/{lang}/LC_MESSAGES/{domain}.static.mo
  │     (msgmerge {lang}.po templates/static.pot → tmp.static.po, msgfmt tmp.static.po → {domain}.static.mo)
  ├── $api->locale->initGettext('.static')  → bind .static domains for all domains (modules + default)
  └── translateHtmlFiles()          → translated .html files (using dgettext("{domain}.static", ...))
```

**Key changes:**
- `compileLanguagePoFiles()`: for each locale, runs `msgmerge {lang}.po templates/server.pot` to a temporary `.po` in the system temp directory, then `msgfmt` → `{domain}.mo`. This produces an `.mo` containing only the PHP strings (intersection of full translations with `server.pot`).
  ```php
  private function compileLanguagePoFiles(): void {
      foreach ($this->locales as $lang) {
          $poFile = $this->domain->serverOutput . "/$lang.po";
          $moFile = $this->domain->serverOutput . "/$lang/LC_MESSAGES/{$this->domain->name}.mo";
          if (!is_dir(dirname($moFile))) {
              mkdir(dirname($moFile), 0777, true) or throw new \RuntimeException("Cannot create directory " . dirname($moFile));
          }
          $tmpPo = tempnam(sys_get_temp_dir(), "{$this->domain->name}.$lang.server.") . '.po';
          $this->exec("msgmerge --no-fuzzy-matching " . escapeshellarg($poFile) . " " . escapeshellarg($this->domain->serverOutput . '/templates/server.pot') . " -o " . escapeshellarg($tmpPo) . " 2>&1", "Merging $poFile with server.pot (msgmerge)");
          $this->exec("msgfmt " . escapeshellarg($tmpPo) . " --strict -o " . escapeshellarg($moFile) . " 2>&1", "Compiling $tmpPo to $moFile (msgfmt)");
          unlink($tmpPo);
          // fuzzy check on original $poFile (same as current logic)
          $contents = (string) file_get_contents($poFile);
          if (strpos($contents, 'fuzzy') !== false) {
              GettextCli::log("🔴 ERROR: $poFile contains fuzzy translations.");
          }
      }
  }
  ```
- New `compileStaticPoFiles()`: for each locale, runs `msgmerge {lang}.po templates/static.pot` to a temporary `.po` in the system temp directory, then `msgfmt` → `{domain}.static.mo`.
  ```php
  private function compileStaticPoFiles(): void {
      foreach ($this->locales as $lang) {
          $poFile = $this->domain->serverOutput . "/$lang.po";
          $moFile = $this->domain->serverOutput . "/$lang/LC_MESSAGES/{$this->domain->name}.static.mo";
          if (!is_dir(dirname($moFile))) {
              mkdir(dirname($moFile), 0777, true) or throw new \RuntimeException("Cannot create directory " . dirname($moFile));
          }
          $tmpPo = tempnam(sys_get_temp_dir(), "{$this->domain->name}.$lang.static.") . '.po';
          $this->exec("msgmerge --no-fuzzy-matching " . escapeshellarg($poFile) . " " . escapeshellarg($this->domain->serverOutput . '/templates/static.pot') . " -o " . escapeshellarg($tmpPo) . " 2>&1", "Merging $poFile with static.pot (msgmerge)");
          $this->exec("msgfmt " . escapeshellarg($tmpPo) . " --strict -o " . escapeshellarg($moFile) . " 2>&1", "Compiling $tmpPo to $moFile (msgfmt)");
          unlink($tmpPo);
      }
  }
  ```
- **No permanent `{lang}.static.po` files exist.** The temporary `.po` files used for `.mo` generation are discarded immediately.
- `$api->locale->initGettext('.static')`: called before HTML translation. `LocaleService::initGettext()` now accepts an optional `$domainSuffix` parameter (default `''`). When called with `'.static'`, it binds `.static` variants for all domains (modules + default). Uses a guard keyed by suffix so repeated calls are no-ops.
- `translateHtmlFiles()`: unchanged logic for DOM manipulation, but:
  - Uses `dgettext($resolvedDomain . '.static', ...)` instead of `dgettext($resolvedDomain, ...)` for HTML translation, where `$resolvedDomain` is the domain from the `@gettext` attribute prefix or `'default'` as fallback
  - This ensures HTML translations don't pollute the runtime `.mo` domain
  - `findFiles()` call updated to `$this->findFiles(FileTypes::HTML)`
- `buildFileDictionary()`, `generateHtmlFile()`, `translateHtmlStrings()`: **unchanged** except domain name
- `normalizeString()`, `mkFileName()`: **unchanged**

### 3.4 `JavascriptExtractor` — removed, merged into `Extractor`

The `JavascriptExtractor` class is deleted. Its logic (extracting JS strings to `install/dist/locale/messages.pot`) is now handled by `Extractor.extractClientPotFile()` writing to `{serverOutput}/templates/client.pot`. The client-side `.pot` is no longer needed separately — the `client.pot` serves both purposes.

The `README.txt` warning against manual edits is removed. The `.pot` → `.po` → `.json` pipeline is fully internal; no human should touch generated directories.

### 3.5 `JavascriptCompiler` — refactored to use `GettextDomain`

**Changes:**
- Constructor accepts `GettextDomain $domain` instead of `string $modulePath`
- `makePoFiles()`:
  - Creates a **temporary `.po`** by merging `{serverOutput}/{lang}.po` with `{serverOutput}/templates/client.pot` via `msgmerge`
  - Uses the existing ad-hoc parser (`convertToJson()` / `parseAsHeaders()`) to convert the temporary `.po` to JSON
  - Outputs JSON to `{clientJsonOutput}/{lang}-{REGION}.json`
  - **Destroys the temporary `.po` immediately after JSON generation**
  ```php
  private function makePoFiles(): void {
      foreach ($this->locales as $lang) {
          $jsLang = Locale::getPrimaryLanguage($lang) . "-" . Locale::getRegion($lang);
          $poFile = $this->domain->serverOutput . "/$lang.po";
          $potFile = $this->domain->serverOutput . "/templates/client.pot";
          $jsonFile = $this->domain->clientJsonOutput . "/$jsLang.json";
          $tmpPo = tempnam(sys_get_temp_dir(), "{$this->domain->name}.$lang.client.") . '.po';
          $output = $this->exec(
              "msgmerge --no-fuzzy-matching " . escapeshellarg($poFile) . " " . escapeshellarg($potFile) . " -o " . escapeshellarg($tmpPo) . " 2>&1",
              "Merging $poFile with $potFile (msgmerge)"
          ) or throw new \RuntimeException("Cannot merge $poFile with $potFile");
          $json = $this->convertToJson(file_get_contents($tmpPo) ?: '');
          file_put_contents($jsonFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
          unlink($tmpPo);
      }
  }
  ```
- `convertToJson()` and `parseAsHeaders()`: **unchanged**

---

## 4. `LocaleService` Changes

### 4.1 `initGettext()` — domain binding once, locale env always

**New logic:**

```php
public function initGettext(string $domainSuffix = ''): void {
    // Always re-set the locale env — this is what actually switches the language
    // (gettext uses the process locale to pick the right .mo file).
    $this->setLocaleEnv();

    // Domain binding is idempotent; only do it once per instance per suffix.
    if (isset($this->domainsInitialized[$domainSuffix])) return;
    $this->domainsInitialized[$domainSuffix] = true;

    // 1. Module domains (from manifest)
    foreach ($api->manifest->modulePaths as $moduleName => $modulePath) {
        $localePath = $api->fs->toPath("module://$moduleName") . '/locale';
        if (is_dir($localePath)) {
            $domain = $moduleName . $domainSuffix;
            bindtextdomain($domain, $localePath);
            bind_textdomain_codeset($domain, 'UTF-8');
            if (!$domainSuffix) {
                $this->testGettext($domain, $localePath);
            }
        }
    }

    // 2. Hard-coded 'default' domain — conflict with a module named 'default' is a real error
    if (isset($api->manifest->modulePaths['default'])) {
        throw new \Zolinga\Intl\Exceptions\GettextDomainException(
            "A module named 'default' conflicts with the built-in 'default' gettext domain."
        );
    }
    $defaultPath = $api->fs->toPath('private://zolinga-intl/default/locale/');
    if (is_dir($defaultPath)) {
        $domain = 'default' . $domainSuffix;
        bindtextdomain($domain, $defaultPath);
        bind_textdomain_codeset($domain, 'UTF-8');
        if (!$domainSuffix) {
            $this->testGettext($domain, $defaultPath);
        }
        if (!$domainSuffix) {
            textdomain('default');
        }
    }
}
```

**Key changes:**
- `setLocaleEnv()` runs unconditionally on every call — required for language switching to take effect
- `bindtextdomain()` calls run only once per instance per suffix via `$this->domainsInitialized` array; subsequent `initGettext()` calls (triggered by language changes) return early after setting the locale env
- New optional `$domainSuffix` parameter (default `''`): when called with `'.static'`, binds `.static` variants for all domains. `testGettext()` is skipped for `.static` bindings.
- Removes the hardcoded `'custom'` domain — replaced by the hard-coded `'default'` domain
- `GettextDomainException` is thrown only for the genuine startup conflict (module named `default`), never on re-init
- The `default` domain's `.mo` files live in `./data/zolinga-intl/default/locale/` (not `./modules/default/locale/`)

### 4.2 No compile-time methods in `LocaleService`

`LocaleService` is a request-time service. The framework calls `$api->locale->initGettext()` automatically at request startup; this handles all runtime domain binding.

Compile-time domain binding (`.static`) is done by calling `$api->locale->initGettext('.static')` from `Compiler::compile()` before HTML translation. The optional `$domainSuffix` parameter on `initGettext()` binds `.static` variants for all domains (modules + default) without duplicating domain discovery logic. It is never called at request time.

> **Note:** `.static` domains are bound for the lifetime of the process. This is acceptable for one-off CLI compilation but means the process should not continue with regular runtime gettext calls after HTML translation.

`JavascriptCompiler` requires no runtime `dgettext()` at all — its `makePoFiles()` is purely file-based (`msgmerge` output → ad-hoc parser → JSON), so no domain binding is needed there either.

`initGettext()` remains the only gettext-related method on `LocaleService`.

---

## 5. `GettextCli` Changes

### 5.1 `extract()` — use `$api->i18n->getGettextDomains()`

```php
public function extract(RequestResponseEvent $event): void {
    $domains = $api->i18n->getGettextDomains();

    // Filter by --module if specified
    if ($moduleFilter = $event->request['module'] ?? null) {
        $domains = array_intersect_key($domains, [$moduleFilter => true]);
    }

    foreach ($domains as $domain) {
        $extractor = new Extractor($domain);
        $extractor->extract();
    }
}
```

No more separate `Extractor` + `JavascriptExtractor` phases — one `Extractor` handles all file types.

### 5.2 `compile()` — use `$api->i18n->getGettextDomains()`

```php
public function compile(RequestResponseEvent $event): void {
    $domains = $api->i18n->getGettextDomains();

    if ($moduleFilter = $event->request['module'] ?? null) {
        $domains = array_intersect_key($domains, [$moduleFilter => true]);
    }

    foreach ($domains as $domain) {
        // Server-side: .po → .mo + HTML translation
        $compiler = new Compiler($domain);
        $compiler->compile();

        // Client-side: .po + client.pot → .json
        $jsCompiler = new JavascriptCompiler($domain);
        $jsCompiler->compile();
    }
}
```

### 5.3 Remove `getTargetModules()`

Replaced by `$api->i18n->getGettextDomains()`.

---

## 6. File Output Structure (New)

### Per module domain (e.g. `acme-module`):

```
modules/acme-module/
  locale/                              ← serverOutput
    templates/
      server.pot                       ← PHP strings only
      client.pot                       ← JS strings only
      static.pot                       ← HTML strings only
    messages.pot                       ← merged (msgcat of all 3)
    en_US.po                           ← from messages.pot (msginit/msgmerge)
    cs_CZ.po
    en_US/LC_MESSAGES/
      acme-module.mo                    ← msgfmt from intersection of en_US.po + server.pot
      acme-module.static.mo             ← msgfmt from intersection of en_US.po + static.pot
    cs_CZ/LC_MESSAGES/
      acme-module.mo
      acme-module.static.mo
  install/dist/locale/                 ← clientJsonOutput
    en-US.json                         ← from temporary intersection of en_US.po + client.pot
    cs-CZ.json
```

### Default domain:

```
data/zolinga-intl/default/locale/         ← serverOutput
  templates/
    server.pot
    client.pot
    static.pot
  messages.pot
  en_US.po                             ← from messages.pot (msginit/msgmerge)
  cs_CZ.po
  en_US/LC_MESSAGES/
    default.mo                         ← msgfmt from intersection of en_US.po + server.pot
    default.static.mo                  ← msgfmt from intersection of en_US.po + static.pot
  cs_CZ/LC_MESSAGES/
    default.mo
    default.static.mo

public/data/zolinga-intl/default/locale/  ← clientJsonOutput
  en-US.json
  cs-CZ.json
```

---

## 7. Complete File Change List

### New files (4):

| File | Purpose |
|------|---------|
| `src/Types/FileTypes.php` | Plain class constants for PHP/JS/HTML file type bitmask |
| `src/Gettext/GettextDomain.php` | Domain descriptor: name, folders, fileTypes, output paths |
| `src/Exceptions/GettextDomainException.php` | Exception for duplicate domain registration |
| `src/I18nService.php` | `$api->i18n` service — `getGettextDomains()` with hard-coded `default` domain |

### Modified files (7):

| File | Changes |
|------|---------|
| `zolinga.json` | Add `i18n` service listener (no config keys — `default` domain is hard-coded) |
| `src/Gettext/GettextAbstract.php` | Constructor takes `GettextDomain`; `findFiles()` scans multiple folders, uses `FileTypes` constants, ignores dot-files/backup extensions; remove `$modulePath`/`$moduleName` |
| `src/Gettext/Extractor.php` | Split extraction into `extractServerPotFile()`/`extractClientPotFile()`/`extractStaticPotFile()` → separate `.pot` files; add `mergePotFiles()`; keep all extraction logic unchanged |
| `src/Gettext/Compiler.php` | Use `GettextDomain`; add `compileStaticPoFiles()` (msgmerge intersection of `{lang}.po` + `templates/static.pot` → temporary `.po`, msgfmt → `.static.mo`); keep DOM/HTML logic unchanged |
| `src/Gettext/JavascriptCompiler.php` | Constructor takes `GettextDomain`; keeps ad-hoc parser for .po → JSON; merge with `client.pot`; output to `clientJsonOutput` |
| `src/LocaleService.php` | `initGettext()`: add optional `$domainSuffix` parameter (default `''`) with guard keyed by suffix; bind `.static` variants when called with `'.static'`; skip `testGettext()` for suffix bindings; replace hardcoded `'custom'` with hard-coded `'default'` domain; add `$domainsInitialized` property; no compile-time methods added |
| `src/GettextCli.php` | Use `$api->i18n->getGettextDomains()`; remove `getTargetModules()`; single-phase extract/compile |

### Deleted files (1):

| File | Reason |
|------|--------|
| `src/Gettext/JavascriptExtractor.php` | Merged into `Extractor` — JS extraction is now `Extractor.extractClientPotFile()` |

### Unchanged files:

| File | Reason |
|------|--------|
| `src/TranslatorService.php` | No gettext changes |
| `src/Events/TranslateEvent.php` | No gettext changes |
| `src/Types/CountryEnum.php` | No gettext changes |
| `src/Types/CountryGroupsEnum.php` | No gettext changes |
| `install/dist/gettext.js` | API unchanged |
| `install/dist/js/web-component-intl.js` | API unchanged |
| `install/dist/vendor/gettext.js/gettext.esm.js` | Third-party |
| All `wiki/` files | Updated separately after implementation |
| All `locale/` files | Generated, not source |

---

## 8. Preserved Logic (Do Not Change)

The following methods/logic must remain functionally identical:

| Class | Method/Logic | What it does |
|-------|-------------|--------------|
| `GettextAbstract` | `loadHtmlFile()` | DOMDocument loading with charset fix |
| `GettextAbstract` | `findTranslatables()` | XPath `//@gettext` query |
| `GettextAbstract` | `parseGettextAttr()` | Parse `[domain:](.\|attr)[#hash]` tags |
| `GettextAbstract` | `getGettextMode()` | Read `<meta name="gettext" content="...">` |
| `GettextAbstract` | `calculateHash()` | SHA1 first 6 chars |
| `Extractor` | `extractInBatches()` | Chunk files into 100, run xgettext |
| `Extractor` | `getExtractCmd()` | Build xgettext command with `bash -c` + `sed` HEREDOC fix — **note:** `exec()` in `GettextAbstract` now uses `chdir(ROOT_DIR)` instead of `chdir($this->modulePath)` |
| `Extractor` | `extractHtmlStrings()` | DOM parse → fake PHP `_()`/`dgettext()` calls; `$relativeFile` now computed from `ROOT_DIR` |
| `Extractor` | `makePhpLine()` | Generate fake PHP gettext line |
| `Compiler` | `compileLanguagePoFiles()` | `msgfmt --strict` logic, fuzzy warnings; uses temporary `.po` in system temp dir merged with `templates/server.pot` |
| `Compiler` | `compileStaticPoFiles()` | msgmerge intersection of `{lang}.po` + `templates/static.pot` → temporary `.po`, then msgfmt → `{domain}.static.mo` |
| `LocaleService` | `initGettext()` | New optional `$domainSuffix` parameter; binds `.static` variants when called with `'.static'`; guard keyed by suffix |
| `Compiler` | `buildFileDictionary()` | Build `"domain:#hash" => strId` map, add hashes to source |
| `Compiler` | `generateHtmlFile()` | Replace vs cherry-pick mode switching |
| `Compiler` | `translateHtmlStrings()` | Apply `dgettext()` translations to DOM nodes |
| `Compiler` | `normalizeString()` | Trim + collapse whitespace |
| `Compiler` | `mkFileName()` | Insert locale before extension |
| `JavascriptCompiler` | `makePoFiles()` | msgmerge temporary `.po` from `{lang}.po` + `client.pot`, ad-hoc parser → JSON, destroy temp `.po` |

---

## 9. Step 2: Global Translation Layer (Future)

After the core refactoring is complete, add an optional global translation layer:

### 9.1 `gettext:extract` — create global merged files

After extracting all domains, create:

```
data/zolinga-intl/default/locale/
  messages.pot          ← msgcat of ALL domain messages.pot files
  en_US.po              ← from messages.pot
  cs_CZ.po
```

### 9.2 `gettext:compile` — merge global translations back

Before compiling each domain:

1. For each locale, merge `default/locale/{lang}.po` into each domain's `{lang}.po` via `msgmerge`
2. Then proceed with normal compilation

This allows translators to work on one big file, with changes flowing back to individual types of translations (PHP/JS/HTML). So huge dictionaries can be managed in one place, but the extraction/compilation logic remains per-domain and per-file-type. 

### Version

Bump up the version to 2.0 and update CHANGELOG.md
