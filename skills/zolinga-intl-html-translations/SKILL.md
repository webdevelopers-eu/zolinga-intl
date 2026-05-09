---
name: zolinga-intl-html-translations
description: Use when writing HTML that needs static translation via gettext attributes. Covers the gettext attribute syntax, gettext-context, nested element translation, meta tag, and translation modes.
argument-hint: "<module-name>"
---

# HTML Static Translations

## Rules When Asked To Make Content Translatable
- When the translation could result in an ambiguous string, use `gettext-context` to disambiguate. Place the context attribute on an element with `gettext` or any ancestor element if it applies to multiple strings. Always try to place it as close to the `gettext` element as possible to avoid unnecessary context on otherwise unambiguous strings.
- Do not mark unnecessarily large portions of text as translatable strings - e.g. large paragraphs or entire pages. If you must, split them into smaller chunks by enclosing them in `<void>` elements (those are removed on output by zolinga-cms) and marking each chunk separately.
- **Always mark ALL translatable content** - including citations, quotes, attribution names, source links, and legal references. Never skip citations or quotes assuming they don't need translation. We strive for COMPLETE translations.
- Use `gettext-context` for short or ambiguous strings that could have different translations depending on context. Examples: "Sign Up" (registration step vs newsletter), "Monitor" (verb vs noun), "sources" (citation toggle vs water sources), CTA buttons like "Start Your Free Trial" vs "Try Risk-Free".
- Use `<void gettext=".">` to split long paragraphs into smaller, independently translatable sentences. This gives translators manageable chunks and allows reusing translations across pages.
- **Keep connected sentences as a single translatable unit.** When a sentence contains inline elements like links, wrap the entire sentence in `<void gettext=".">` (or a single parent with `gettext="."`), not each inline element separately. Splitting a sentence into disconnected parts breaks translation context and produces poor results. Example:
  ```html
  <!-- WRONG: two disconnected parts of one sentence -->
  <span gettext=".">Please download the PDF to view it:</span>
  <a href="file.pdf" gettext=".">Download PDF</a>

  <!-- CORRECT: one translatable unit with nested link as placeholder -->
  <void gettext=".">Please download the PDF to view it: <a href="file.pdf">Download PDF</a></void>
  ```
- Do best effort and best judgment. If you are unsure, ask for clarification.

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

## Translator Comments

You can add comments for translators using HTML comments starting with `TRANSLATORS:`. These comments are extracted and included in `.pot`/`.po` files to provide context and instructions.

**Two supported positions:**

1. **Before the @gettext element** (traditional):
```html
<!-- TRANSLATORS: This is a call-to-action button for the free trial signup -->
<button gettext=".">Start Your Free Trial</button>
```

2. **Nested inside @gettext element** (new): Place as first child(ren) before any text or elements:
```html
<span gettext="."><!-- TRANSLATORS: Main headline -->Welcome to IPDefender</span>

<div gettext=".">
  <!-- TRANSLATORS: Keep the trademark symbol ® -->
  <!-- TRANSLATORS: This is a registered trademark -->
  IPDefender® Protection
</div>
```

**Rules:**
- Must start with `TRANSLATORS:` (case-sensitive)
- Can be placed before the element OR as first child(ren) inside the element (before any text/non-comment nodes)
- Must be standard HTML comments `<!-- ... -->`
- Multiple comments are concatenated in the `.po` file

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

## Splitting Long Paragraphs with `<void>`

Use `<void>` elements to break long paragraphs into smaller translatable chunks. The `<void>` tags are removed on output, so the rendered HTML is identical:

```html
<p>
    <void gettext=".">If a dispute goes to court, judges rely on this evidence to confirm your ownership.</void>
    <void gettext=".">Without proper documentation, it becomes much harder to prove your rights.</void>
    <void gettext=".">This requirement is based on trademark laws that grant protection only when the brand owner actively defends their mark.</void>
</p>
```

This gives translators manageable sentences instead of one huge block, and allows reusing translations across pages.

## Marking Citations and Quotes

Always mark citations, quotes, and attribution text as translatable. Legal citations and source text need translation just like any other content:

```html
<cite itemprop="name" gettext=".">
    Federal Trade Commission: Corrected Trial Brief, 2021
</cite>
<quote cite="https://example.com" gettext=".">
    Therefore, once acquired, trademark rights may be lost or weakened...
</quote>
<a role="show-sources" gettext-context="citation toggle" gettext=".">sources</a>
```

## After Marking Strings

Run `bin/zolinga gettext:extract --domains=my-module,default` to generate `.po` files, then translate and compile.

**Important**: `gettext:extract` modifies source HTML files in place — each keyword in every `gettext` attribute receives a `#`-prefixed 6-character hash suffix that uniquely identifies the element. For example, `gettext="."` becomes `gettext=".#a3f2b1"` and `gettext=". title"` becomes `gettext=".#d2bc00 title#1396ff"`. These hashes link source elements to their translations across files. Commit the updated source files after extraction.

Note that all translations not in a module but in data folders like `data/` or `public/data` can be translated using the built-in `default` domain. Use `--domains=default` to extract and compile these translations.

## Testing

For testing the translation workflow without affecting production domains, use the `test` domain:

```bash
bin/zolinga gettext:extract --domains=test
bin/zolinga gettext:compile --domains=test
```

The `test` domain scans files and creates output in `data/zolinga-intl/gettext-test/` folder. This is ideal for testing behavior and experimenting with the translation pipeline.

See also: **zolinga-intl-multilingual-support** for the full pipeline.