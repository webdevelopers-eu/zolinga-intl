---
name: zolinga-intl-html-translations
description: Use when writing HTML that needs static translation via gettext attributes. Covers the gettext attribute syntax, gettext-context, nested element translation, meta tag, and translation modes.
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
|---------|----------|
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

## Context with gettext-context

Use `gettext-context` on an element or any ancestor to disambiguate identical strings:

```html
<div>
  <a gettext-context="navigation" gettext=".">Home</a>
</div>
<div gettext-context="homepage">
  <h1 gettext=".">Home</h1>
</div>
```

The extractor uses the closest `gettext-context` attribute on the element or its ancestors as the `msgctxt` in `.po` files.

## Nested Element Translation

The `gettext="."` attribute works on elements containing **other elements** too. Child elements become numbered placeholders:

```html
<div gettext=".">Click <a href="/">here</a> to go to <i>homepage</i>.</div>
```

This appears in `.po` as: `Click <1>here</1> to go to <2>homepage</2>.`

The translator keeps the placeholders and translates around them:

```
msgstr "Kliknete <1>zde</1> pro navstevu <2>domaci stranky</2>."
```

The compiler expands placeholders back to full HTML:

```html
<div gettext=".">Kliknete <a href="/">zde</a> pro navstevu <i>domaci stranky</i>.</div>
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

Run `bin/zolinga gettext:extract --domains=my-module,default` to generate `.po` files, then translate and compile.

**Important**: `gettext:extract` modifies source HTML files in place — each keyword in every `gettext` attribute receives a `#`-prefixed 6-character hash suffix that uniquely identifies the element. For example, `gettext="."` becomes `gettext=".#a3f2b1"` and `gettext=". title"` becomes `gettext=".#d2bc00 title#1396ff"`. These hashes link source elements to their translations across files. Commit the updated source files after extraction.

Note that all translations not in a module but in data folders like `data/` or `public/data` can be translated using the built-in `default` domain. Use `--domains=default` to extract and compile these translations.

See also: **zolinga-intl-multilingual-support** for the full pipeline.