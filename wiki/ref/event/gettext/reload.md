Priority: 0.6

# Gettext Reload Event

This event is a part of the [Zolinga Internationalization](:Zolinga Intl) module. It is triggered from `cli` origin (command line) by the `bin/zolinga gettext:reload` command. The command re-initializes gettext domains in the current PHP process so that any freshly compiled `.mo` files are picked up without restarting the server.

## What it does

The handler calls:

```php
$api->locale->initGettext(reload: true);
$api->locale->initGettext(prefix: '.static', reload: true);
```

This re-binds both the regular server-side domains and the `.static` (HTML translation) domains against the on-disk `.mo` files.

## Usage

```bash
bin/zolinga gettext:reload
```

Typical workflow after translating strings:

```bash
bin/zolinga gettext:compile
bin/zolinga gettext:reload
```

Related:
- [Gettext Compile Event](:ref:event:gettext:compile)
- [Gettext Extract Event](:ref:event:gettext:extract)
- [Zolinga Internationalization Module](:Zolinga Intl)
