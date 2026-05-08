---
name: zolinga-intl-php-translations
description: Use when writing PHP code that needs localized strings. Covers dgettext, dngettext, context separator, and static analysis constraints.
argument-hint: "<module-name>"
---

# PHP Translations

## Single String — dgettext()

```php
echo dgettext('my-module', 'Hello, world!');
```

The domain is always the **module folder name** (e.g. `ipdefender`, `zolinga-cms`).

## Plural Forms — dngettext()

```php
echo sprintf(dngettext('my-module', 'One apple', '%d apples', $count), $count);
```

## Context Separator

When the same English word needs different translations in different contexts, append `"\x04"` before the message:

```php
echo dgettext('my-module', "Confirm form submission\x04Send");
echo dgettext('my-module', "Email transmission\x04Send");
```

## Static Analysis Rule

**Never use variables inside translatable strings.** The extractor must find literal strings at parse time.

Wrong:
```php
// FAILS — extractor cannot see the variable value
dgettext('my-module', "Found $count records");
dgettext('my-module', "Email transmission" . GETTEXT_CTX_END . "Send");
```

Right:
```php
// OK — literal string, variable substituted at runtime
sprintf(dgettext('my-module', 'Found %d records'), $count);
dgettext('my-module', "Email transmission\x04Send");
```

## After Marking Strings

Run `bin/zolinga gettext:extract --domains=my-module` to generate `.po` files, then translate and compile.

See also: **zolinga-intl-multilingual-support** for the full pipeline.