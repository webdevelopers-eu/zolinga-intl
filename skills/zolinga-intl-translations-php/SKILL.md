---
name: zolinga-intl-translations-php
description: Use when writing PHP code that needs localized strings. Covers dgettext, dngettext, context separator, enum label convention, and static analysis constraints.
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

When the same English word needs different translations in different contexts, prepend the context and `"\x04"` to the message:

```php
echo dgettext('my-module', "Confirm form submission\x04Send");
echo dgettext('my-module', "Email transmission\x04Send");
```

## Enum Labels — MUST Use Class Name as Context

For any PHP `enum` that returns user-facing labels via `dgettext()`, the msgid **MUST** be prefixed with `EnumClassName\x04`. This disambiguates short labels (e.g. "Draft", "Pending") that appear across multiple enums.

```php
// BAD — no context, translator cannot tell which "Active" this is
self::SUBSCRIBED => dgettext('ipdefender-base', 'Active Subscription'),

// GOOD — enum class name provides unambiguous context
self::SUBSCRIBED => dgettext('ipdefender-base', "AccountStatusEnum\x04Active Subscription"),
```

Migrate existing enums by adding the `EnumName\x04` prefix to all `dgettext()` calls inside them. After the change, re-run extraction.

## Translator Comments

Add comments for translators by placing a PHP comment starting with `TRANSLATORS:` immediately before the gettext call. These comments are extracted and included in `.pot`/`.po` files:

```php
// TRANSLATORS: This is a call-to-action button label for the free trial signup
echo dgettext('my-module', 'Start Your Free Trial');

// TRANSLATORS: "Send" here refers to sending an email
echo dgettext('my-module', "Email transmission\x04Send");

// TRANSLATORS: Label used when an invoice has no end date — shown in the invoice list as 'ongoing'.
echo dgettext('my-module', "InvoiceStatusEnum\x04Ongoing");
```

Rules for translator comments:
- Start with `TRANSLATORS:` (case-sensitive)
- Placed immediately before the gettext call
- Use standard PHP comment syntax (`//` or `#`)
- Write each comment as a self-contained sentence — do not say "Same as above" or "See above"
- Include information about placeholders (e.g. `%s`, `%d`) and what they represent
- Mention where the string appears in the UI if helpful

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
