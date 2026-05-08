Priority: 0.6

# Gettext Extraction Event

This event is a part of the [Zolinga Internationalization](:Zolinga Intl) module. It is triggered from `cli` origin (command line) by the `bin/zolinga gettext:extract` command. The command extracts the translatable strings from PHP, HTML and JavaScript files and saves them to `{MODULE}/locale/{LOCALE}.po` plain text files for translators to translate.

## Extraction Pipeline

For each domain, extraction produces three separate `.pot` template files:

| File | Source | Extracted From |
|------|--------|----------------|
| `{serverOutput}/templates/server.pot` | PHP | `*.php` files with `dgettext()`, `dngettext()` calls |
| `{serverOutput}/templates/client.pot` | JavaScript | `*.js`, `*.mjs` files with `gettext()`, `ngettext()`, `__()`, `_n()` calls |
| `{serverOutput}/templates/static.pot` | HTML | `*.html` files with `<meta name="gettext" content="translate"/>` and `gettext` attributes |

These three `.pot` files are then merged via `msgcat` into `{serverOutput}/messages.pot`, which is used to create/update the per-locale `.po` files.

Each `.pot` file is also post-processed to split `"context\x04message"` entries into proper `msgctxt`/`msgid` pairs (since `xgettext` does not handle the `\x04` context separator natively).

Usage:

```bash
bin/zolinga gettext:extract [--domains={DOMAINS}]
```

`--domains` accepts a comma-separated list of domain names (e.g. `--domains=mod1,mod2`).

When no `--domains` option is provided, the command extracts translations for all domains (module domains plus the built-in `default` domain).

Related:
- [Gettext Compile Event](:ref:event:gettext:compile)
- [Zolinga Internationalization Module](:Zolinga Intl)
