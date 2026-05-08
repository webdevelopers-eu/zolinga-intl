---
name: zolinga-intl-html-translations
description: Use when writing HTML that needs static translation via gettext attributes. Covers the gettext attribute syntax, meta tag, and translation modes.
argument-hint: "<module-name>"
---

# HTML Static Translations

## Setup

Add `<meta name="gettext" content="translate"/>` in `<head>`:

```html
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="gettext" content="translate"/>
    <title gettext=".">My Page</title>
  </head>
  <body>
    <h1 gettext=".">Hello, World!</h1>
    <img alt="Company logo" gettext="alt" src="logo.png"/>
  </body>
</html>
```

## gettext Attribute Syntax

The `gettext` attribute is a whitespace-separated list of keywords:

| Keyword | Meaning |
|---------|---------|
| `.` | Translate element's text content |
| `title` | Translate the `title` attribute |
| `alt` | Translate the `alt` attribute |
| `content` | Translate the `content` attribute (for `<meta>`) |
| `my-module:title` | Use domain `my-module` instead of default |
| `.#a3f2b1` | Hash suffix (added by compiler, do not add manually) |

Examples:

```html
<h1 gettext=".">Hello</h1>
<a href="#" gettext="." title="Go home">Home</a>
<img alt="Logo" gettext="alt" src="logo.png"/>
<meta name="description" content="My site" gettext="content"/>
<span gettext="my-module:.">Domain-specific text</span>
```

## Translation Modes

The `<meta name="gettext" content="..."/>` in the **generated** file controls behavior on recompilation:

| Mode | Behavior |
|------|----------|
| `replace` | File is fully regenerated from source on every compile. **Default for new files.** Manual edits are lost. |
| `cherry-pick` | Only elements with `gettext` attribute are updated; all other HTML is preserved. Good for large articles where translators need full control of layout. |
| (no meta) | File is ignored by compiler — fully manual maintenance. |

**Warning**: Cherry-pick mode does NOT sync structural changes (CSS, images, new elements) from the source file — only translatable strings are updated. Use `replace` mode if you want structural changes reflected in the translated file.

## After Marking Strings

Run `bin/zolinga gettext:extract --domains=my-module` to generate `.po` files, then translate and compile.

See also: **zolinga-intl-multilingual-support** for the full pipeline.