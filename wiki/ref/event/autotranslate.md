# autotranslate

**Origin:** cli  
**Event:** `autotranslate`  
**Class:** `Zolinga\Intl\AutotranslateCli`  
**Method:** `run`

## Purpose

Runs the full gettext autotranslate pipeline in one step:

1. **gettext:extract** — extract translatable strings from source files
2. **gettext:autotranslate** — AI-translate untranslated entries
3. **gettext:compile** — compile `.po` files into `.mo` and `.json`

If any step fails, the pipeline stops and returns the failing step's status.

## Usage

```bash
# Process all domains
bin/zolinga autotranslate

# Process specific domains
bin/zolinga autotranslate --domains=ipdefender,zolinga-cms

# Process all domains explicitly
bin/zolinga autotranslate --all
```

All request parameters (`--domains`, `--all`, etc.) are forwarded to each sub-event unchanged.

## Request Parameters

| Parameter | Type   | Description                                      |
|-----------|--------|--------------------------------------------------|
| `domains` | string | Comma-separated list of gettext domains to process |
| `all`     | bool   | Process all domains (default behavior if neither `domains` nor `all` is set) |