Priority: 0.6

# Gettext Compile Event

This event is a part of the [Zolinga Internationalization](:Zolinga Intl) module. It is triggered from `cli` origin (command line) by the `bin/zolinga gettext:compile` command. The command compiles the translations for a module.

## Compilation Pipeline

For each domain, compilation produces three types of output:

### 1. Server-side (.mo)

For each locale with a `.po` file, the compiler:
1. Merges `{lang}.po` with `templates/server.pot` via `msgmerge --no-fuzzy-matching` → temporary `.po`
2. Compiles the temporary `.po` via `msgfmt --strict` → `{serverOutput}/{lang}/LC_MESSAGES/{domain}.mo`

### 2. Static HTML (.static.mo)

For each locale with a `.po` file, the compiler:
1. Merges `{lang}.po` with `templates/static.pot` via `msgmerge --no-fuzzy-matching` → temporary `.po`
2. Compiles the temporary `.po` via `msgfmt --strict` → `{serverOutput}/{lang}/LC_MESSAGES/{domain}.static.mo`
3. Binds `.static` domains via `$api->locale->initGettext('.static')`
4. Translates HTML files marked with `<meta name="gettext" content="translate"/>` → generates `*.{lang-REGION}.html` files

### 3. Client-side (.json)

For each locale with a `.po` file, the compiler:
1. Merges `{lang}.po` with `templates/client.pot` via `msgmerge --no-fuzzy-matching` → temporary `.po`
2. Parses the temporary `.po` via ad-hoc parser → `{clientJsonOutput}/{lang-REGION}.json`
3. Destroys the temporary `.po`

Usage:

```bash
bin/zolinga gettext:compile [--domains={DOMAINS}]
```

`--domains` accepts a comma-separated list of domain names (e.g. `--domains=system,default`).

When no `--domains` option is provided, the command compiles translations for all domains (module domains plus the built-in `default` domain).

Related:
- [Gettext Extraction Event](:ref:event:gettext:extract)
- [Zolinga Internationalization Module](:Zolinga Intl)