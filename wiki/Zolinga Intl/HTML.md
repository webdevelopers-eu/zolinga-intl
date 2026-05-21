# HTML Translation

Zolinga can statically translate HTML files — useful for email templates, legal documents, [Zolinga CMS](https://github.com/webdevelopers-eu/zolinga-cms/) static HTML pages, or any page with lots of text. Mark strings in the source HTML, extract them into `.po` files, translate, then compile localized copies.

## Quick Start

**1. Mark your HTML file for translation** — add the meta tag and `gettext` attributes:

```html
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="gettext" content="translate"/>
    <title gettext=".">My Page</title>
    <meta name="description" content="About us" gettext="content"/>
  </head>
  <body>
    <h1 gettext=".">Hello, World!</h1>
    <img src="logo.png" alt="Company logo" gettext="alt"/>
    <p gettext=". title" title="Hover text">Paragraph with a tooltip.</p>
  </body>
</html>
```

**Available translation attributes:**

| Attribute | Where | Purpose |
|---|---|---|
| `gettext="keywords"` | Any element | Space-separated list of what to translate (see below) |
| `gettext-context="text"` | Element or ancestor | Disambiguates identical strings — maps to `msgctxt` in `.po` |
| `gettext-context-adjacent="N"` | Element or ancestor | Adds up to N surrounding translatable elements as translator hints |

**`gettext` keyword values** (space-separated):
- `.` — the element's text content
- `alt`, `title`, `content`, … — any HTML attribute by name
- `my-module:alt` — same but stored under the `my-module` domain (see [Domain Prefix](#domain-prefix))

**2. Extract strings** to `.po` files:

```bash
bin/zolinga gettext:extract --domains=MyModule
```

This adds `#HASH` suffixes to each `gettext` attribute in the source file and creates/updates `MyModule/locale/cs_CZ.po` etc. Commit the modified source file.

**3. Translate** — edit the `.po` files, fill in `msgstr` for each `msgid`.

Or use AI autotranslation (requires [zolinga-ai](https://github.com/webdevelopers-eu/zolinga-ai/)):

```bash
bin/zolinga gettext:autotranslate --domains=MyModule
```

This fills in all untranslated `msgstr` entries automatically. It saves progress after each entry, so interrupted runs resume where they left off.

**4. Compile** localized HTML files:

```bash
bin/zolinga gettext:compile --domains=MyModule
```

This creates `index.cs-CZ.html`, `index.fr-FR.html`, … next to the source file.

---

This `bin/zolinga autotranslate [--domains=domain,...] [--all]` (requires `zoliga-ai`) command executes all at once: `gettext:extract`, then `gettext:autotranslate`, then `gettext:compile`. Use it for a one-command update when you don't care about manually editing `.po` files.

---

## Domain Prefix

By default every translatable string belongs to the domain of the module that owns the HTML file. Use a `domain:keyword` prefix to override this per element:

```html
<h1 gettext=".">Belongs to this module's domain</h1>
<meta name="cms.title" content="My Page" gettext="default:content"/>
<span gettext="other-module:.">Belongs to other-module's domain</span>
```

The `default` domain is the built-in shared domain. Use it for strings in HTML files that live outside any module (e.g. in `data/` or `public/`):

```bash
bin/zolinga gettext:extract --domains=default
bin/zolinga gettext:compile --domains=default
```

---

## Nested Elements

`gettext="."` works even when child elements are present. They become numbered placeholders:

```html
<p gettext=".">Click <a href="/">here</a> to return to <i>home</i>.</p>
```

In the `.po` file: `Click <1>here</1> to return to <2>home</2>.`

The translator keeps the placeholders; the compiler restores them to real HTML.

---

## Splitting Long Text with `<void>`

Instead of translating an entire paragraph as one giant string, split it into sentences using `<void>` elements. The `<void>` tags are removed on output, so the rendered HTML is unchanged:

```html
<p>
  <void gettext=".">First sentence of the policy.</void>
  <void gettext=".">Second sentence of the policy.</void>
  <void gettext=".">Third sentence of the policy.</void>
</p>
```

This gives translators manageable chunks and allows reusing the same sentence across multiple pages.

---

## Disambiguation with Context

When the same string means different things in different places, use `gettext-context`:

```html
<nav gettext-context="navigation">
  <a gettext=".">Home</a>
</nav>

<main gettext-context="homepage">
  <h1 gettext=".">Home</h1>
</main>
```

The attribute can be placed on the element itself or any ancestor. The closest ancestor wins.

---

## Adjacent Context for Translators

When translating sentence-by-sentence (e.g. legal text), give translators surrounding context with `gettext-context-adjacent="N"`:

```html
<div gettext-context-adjacent="2">
  <void gettext=".">First sentence.</void>
  <void gettext=".">Second sentence.</void>
  <void gettext=".">Third sentence.</void>
</div>
```

Each sentence gets up to 2 preceding and 2 following sentences added as `TRANSLATORS:` comments in the `.po` file.

---

## Translator Comments

Add notes for translators using HTML comments starting with `TRANSLATORS:`. They end up in the `.po` file and are stripped from HTML served to visitors.

**Before the element:**
```html
<!-- TRANSLATORS: This is the CTA button for the free trial signup -->
<button gettext=".">Start Free Trial</button>
```

**Nested inside the element (before any text):**
```html
<h1 gettext="."><!-- TRANSLATORS: Main hero heading -->Welcome</h1>
```

**On any ancestor (inherited by all descendants):**
```html
<!-- TRANSLATORS: Legal text — do not translate trademark names -->
<article>
  <h1 gettext=".">Our Terms</h1>
  <p gettext=".">IPDefender® is a registered trademark.</p>
</article>
```

---

## Keeping Translated Files Up to Date

Generated translated files contain `<meta name="gettext" content="replace"/>`. This tells the compiler to regenerate the file from scratch on the next compile run. Do not manually edit these files.

Three modes you can set in a translated file's meta tag:

| `content` value | Behavior |
|---|---|
| `translate` | Source file — never overwrite |
| `replace` | Fully regenerate on next compile (default for generated files) |
| `cherry-pick` | Preserve manual edits; only update elements that still have a `gettext` attribute |

Use `cherry-pick` when you want to maintain a large translated page manually but still auto-update a few strings (e.g. the `<title>`):

```html
<meta name="gettext" content="cherry-pick"/>
<title gettext=".#a3f2b1">Naše stránka</title>
<h1>Ručně psaný nadpis</h1>  <!-- left alone -->
```

---

## Testing

To experiment without touching production `.po` files, use the `test` domain:

```bash
bin/zolinga gettext:extract --domains=test
bin/zolinga gettext:compile --domains=test
```

It parses `.html` and other files in `data/zolinga-intl/gettext-test/` and also creates output in this directory. This is a safe sandbox for testing new features or debugging issues without affecting real translation files.

---

## Web Components

If your web component loads HTML templates via `loadContent()`, use the intl-aware base class instead of the standard one:

```js
import '/dist/zolinga-intl/js/web-component-intl.js';
```

It extends `WebComponent` and automatically loads the localized `.html` variant (e.g. `my-template.cs-CZ.html`) when available.
