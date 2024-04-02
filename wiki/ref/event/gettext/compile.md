Priority: 0.6

# Gettext Compile Event

This event is a part of the [Zolinga Internationalization](:Zolinga Intl) module. It is triggered from `cli` origin (command line) by the `bin/zolinga gettext:compile` command. The command compiles the translations for a module. The translations are compiled to `{MODULE}/locale/{LOCALE}/LC_MESSAGES/{MODULE}.mo` files for PHP, `{MODULE}/install/dist/locale/{LOCALE}.json` dictionaries for JavaScript or `*.{lang}_{TERRITORY}.html` static translations for HTML.

Usage:

```bash
bin/zolinga gettext:compile [--module={MODULE}]
```

When no `--module` option is provided, the command compiles translations for all modules.

Related:
- [Gettext Extraction Event](:ref:event:gettext:extract)
- [Zolinga Internationalization Module](:Zolinga Intl)