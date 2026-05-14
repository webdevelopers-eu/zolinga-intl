---
name: zolinga-intl-translations-php
description: Use when writing PHP code that needs localized strings. Covers dgettext, dngettext, context separator, and static analysis constraints.
argument-hint: "<module-name>"
---

# PHP Translations

## Single String — dgettext()

```php
echo dgettext('my-module', 'Hello, world!');
```

The domain is always the **module folder name** (e.g. `ipdefender`, `zolinga-cms`).

## CRITICAL: Do NOT use `_()` in PHP

- Always use `dgettext('module', '...')` for single strings and `dngettext('module', 'one', 'many', $n)` for plurals. The domain **must** be the module folder name in which the PHP file resides (for example `ipdefender`, `ipdefender-base`, `zolinga-rms`, `system`).
- If you find usages of the shorthand `_('...')`, replace them with `dgettext('module', '...')`. Example:

```php
// BAD
echo _('Settings loaded');

// GOOD (in module 'zolinga-rms')
echo dgettext('zolinga-rms', 'Settings loaded');
```

When converting formatted strings, keep `sprintf` wrapping the gettext call:

```php
// BAD
throw new \InvalidArgumentException(_('Username %s is already taken'), 403);

// GOOD
throw new \InvalidArgumentException(sprintf(dgettext('my-module', 'Username %s is already taken'), $username), 403);
```

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

## Translator Comments

Add comments for translators by placing a PHP comment starting with `TRANSLATORS:` immediately before the gettext call. These comments are extracted and included in `.pot`/`.po` files:

```php
// TRANSLATORS: This is a call-to-action button label for the free trial signup
echo dgettext('my-module', 'Start Your Free Trial');

// TRANSLATORS: "Send" here refers to sending an email
echo dgettext('my-module', "Email transmission\x04Send");
```

The comment must:
- Start with `TRANSLATORS:` (case-sensitive, singular form for PHP/JS)
- Be placed immediately before the gettext call
- Use standard PHP comment syntax (`//` or `#`)

Multiple comments can be used and will be concatenated in the `.po` file.

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
