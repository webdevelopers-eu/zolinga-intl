Priority: 0.6

# Gettext Extraction Event

This event is a part of the [Zolinga Internationalization](:Zolinga Intl) module. It is triggered from `cli` origin (command line) by the `bin/zolinga gettext:extract` command. The command extracts the translateable strings from PHP, HTML and JavaScript files and saves them to `{MODULE}/locale/{LOCALE}.po` plain text files for translators to translate.

Usage:

```bash
bin/zolinga gettext:extract [--module={MODULE}]
```

When no `--module` option is provided, the command extracts translations for all modules.

Related:
- [Gettext Compile Event](:ref:event:gettext:compile)
- [Zolinga Internationalization Module](:Zolinga Intl)
