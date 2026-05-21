# Overview

You may choose to generate static translations of your static HTML resources. This is very useful for more complex HTML resources with a lot of text.

To do this, you need to mark the HTML file for translation and then run the `bin/zolinga gettext:extract` command to extract the translatable strings from the HTML file.
Translate the strings and then run the `bin/zolinga gettext:compile` command to generate translated HTML files. If you have configured the [zolinga-ai](https://github.com/webdevelopers-eu/zolinga-ai/) integration, you can also use the `bin/zolinga autotranslate` command to automatically executes all three steps in one go: extract, auto-translate using AI, and compile.

# Why?

Sometimes you need to translate large swaths of text in your HTML files that are not easily translatable using the `gettext` function. For example legal documents, articles, or other large blocks of contextual texts. In these cases, it is easier to translate the whole HTML file rather than trying to extract the translatable strings and then reassemble them.

Another use case is when you need to translate for example e-mail templates. It is easier to have statically generated translations of these templates than to use the `gettext` function on-the-fly to translate them using PHP or JavaScript. 

# Marking HTML Files for Translation

To mark an HTML file for translation, you need to add the `<meta name="gettext" content="translate"/>` tag to the `<head>` section of the HTML file and mark translateable strings with the `gettext` attribute.

For example:

```html
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="gettext" content="translate"/>
    <title gettext=".">My HTML Page</title>
    <meta name="description" content="My HTML Page" gettext="content"/>
  </head>
  <body>
    <h1 gettext=".">Hello, World!</h1>
    <img alt="Company logo" gettext="alt" src="logo.png"/>
  </body>
</html>
```

The `gettext` attribute is used to mark the translatable strings. The value of the `gettext` attribute is a list of white-space separated keywords. Keywords are
used to identify the translatable strings in the element. It can be either the name of an attribute to be translated or a dot (`.`) to indicate that the element's text content should be translated.

Examples:

```html
<p gettext=".">This is a translatable paragraph.</p>
<p gettext="title ." title="Will be translated too">This is a translatable paragraph with a title.</p>
```

# Context with gettext-context

Use the `gettext-context` attribute on an element or any ancestor to disambiguate identical strings. The extractor uses the closest `gettext-context` attribute as the `msgctxt` in `.po` files:

```html
<div gettext-context="navigation">
  <a gettext=".">Home</a>
</div>
<div gettext-context="homepage">
  <h1 gettext=".">Home</h1>
</div>
```

# Adjacent Context with gettext-context-adjacent

Use `gettext-context-adjacent="N"` on a translatable element or any ancestor (`ancestor-or-self::*`) to include the text of up to `N` preceding and `N` following translatable elements as `TRANSLATORS` comments. This gives translators surrounding context without making those elements part of the same translatable unit.

The search uses XPath `preceding::*[@gettext]` and `following::*[@gettext]` axes, so it traverses the entire document in **HTML source order regardless of nesting depth**. It is not limited to siblings.

```html
<div gettext-context-adjacent="1">
  <span gettext=".">Previous item</span>
  <span gettext=".">This item</span>
  <span gettext=".">Next item</span>
</div>
```

This produces a comment like:

```
// TRANSLATORS: Adjacent context (-1): "Previous item"
// TRANSLATORS: Adjacent context (+1): "Next item"
```

The traversal collects up to `N` translatable elements in each direction, skipping non-translatable elements. Only elements with a `gettext` attribute are considered.

This is especially useful for large texts (terms of service, legal documents) where each sentence is a separate translatable `<void>` element. Setting `gettext-context-adjacent="3"` on a container gives translators surrounding context for every sentence inside it:

```html
<div gettext-context-adjacent="3">
  <void gettext=".">First sentence of terms.</void>
  <void gettext=".">Second sentence of terms.</void>
  <void gettext=".">Third sentence of terms.</void>
  <void gettext=".">Fourth sentence of terms.</void>
</div>
```

Each sentence will include up to 3 preceding and 3 following translatable elements as context.

# Translator Comments

You can add comments for translators using HTML comments starting with `TRANSLATORS:`. These comments are extracted and included in `.pot`/`.po` files to provide context and instructions.

## Three Supported Positions

### 1. Before the @gettext Element (Traditional)

Place comments as preceding siblings immediately before an element with a `gettext` attribute:

```html
<!-- TRANSLATORS: This is a call-to-action button for the free trial signup -->
<button gettext=".">Start Your Free Trial</button>

<!-- TRANSLATORS: "sources" here refers to the list of citation sources, not water sources -->
<a role="show-sources" gettext-context="citation toggle" gettext=".">sources</a>
```

### 2. Nested Inside @gettext Element

Place comments as the **first child node(s)** inside the @gettext element, before any text or non-comment nodes:

```html
<span gettext=".">
  <!-- TRANSLATORS: This is the main headline for the hero section -->
  Welcome to IPDefender
</span>

<div gettext=".">
  <!-- TRANSLATORS: Keep the trademark symbol ® in the translation -->
  <!-- TRANSLATORS: This is a registered trademark in EU and US -->
  IPDefender® Protection
</div>
```

### 3. Inherited from Any Ancestor Element

Place comments as preceding siblings immediately before any ancestor element's opening tag, or nested as first child(ren) immediately after an ancestor's opening tag. These comments are inherited by **all** translatable elements inside those ancestors:

```html
<!-- TRANSLATORS: This is a legal disclaimer that applies to the entire article -->
<!-- TRANSLATORS: Do not translate trademark names -->
<article>
  <h1 gettext=".">Welcome to IPDefender</h1>
  <p gettext=".">IPDefender® provides trademark protection.</p>
  <section>
    <h2 gettext=".">Our Services</h2>
    <p gettext=".">We offer comprehensive trademark monitoring.</p>
  </section>
</article>
```

All translatables inside the `<article>` will inherit both comments. This is useful for:
- Legal disclaimers
- Brand guidelines
- Tone instructions
- Domain-specific terminology rules

Comments placed before **any** ancestor element (not just `<body>`, `<html>`, `<head>`, `<article>`, `<section>`) are inherited. For example, a comment before a `<div>` or `<header>` will also propagate to all descendant translatables:

```html
<!-- TRANSLATORS: This section contains pricing information -->
<div class="pricing">
  <h2 gettext=".">Our Plans</h2>
  <p gettext=".">Choose the plan that fits your needs.</p>
</div>
```

## Extraction Mechanism (in order)

For each translatable element, the extractor collects `TRANSLATORS:` comments in this exact order, then deduplicates:

1. **Auto-generated location comment** — always added first: `// TRANSLATORS: This string's location in the HTML source code is /html/body/...`
2. **Adjacent context** — from `gettext-context-adjacent` attribute (if set)
3. **SOURCE comment** — auto-generated reference to the source file
4. **Comments immediately after the element opening tag** — via `getNestedTranslatorsComments()`: collects consecutive comment child nodes at the start of the element and stops when encountering non-empty text or a non-comment element.
5. **Comments immediately before the opening tag of the element and of any ancestor** — via `getPrecedingTranslatorsComments()` called for each node in the `ancestor-or-self::*` XPath axis. For each node, it collects consecutive preceding-sibling comment nodes until the first stopper.

**Deduplication:** After collecting all comments from steps 1-5, `array_unique(array_filter(...))` removes duplicates and empty entries.

## Comment Rules

The comment must:
- Start with `TRANSLATORS:` (case-sensitive)
- Be placed in one of three positions:
  1. Immediately before the element with the `gettext` attribute (as preceding siblings)
  2. As the first child(ren) inside the element with the `gettext` attribute (before any text or non-comment nodes)
  3. Before **any** ancestor element (as preceding siblings) or as first child(ren) inside any ancestor — inherited by all descendant translatables
- Be in a standard HTML comment `<!-- ... -->`

Multiple comments can be used and will be concatenated in the `.po` file. This is useful for providing additional context, usage notes, or special instructions to translators.

## Important Security Note

**TRANSLATORS comments are automatically removed by zolinga-cms when serving pages to visitors.** This is intentional - these comments may contain sensitive information such as:
- Internal instructions to translators
- Context about marketing strategy
- Legal disclaimers not meant for public display
- Brand guidelines

The comments are extracted during the gettext extraction phase (`bin/zolinga gettext:extract`) and included in `.pot`/`.po` files, but are stripped from the final HTML output before it's sent to the browser.

## Examples

```html
<!-- Single comment before element -->
<!-- TRANSLATORS: This appears in the navigation menu -->
<a gettext=".">Home</a>

<!-- Multiple comments before element -->
<!-- TRANSLATORS: This is a legal disclaimer -->
<!-- TRANSLATORS: Do not translate the trademark name -->
<p gettext=".">IPDefender® is a registered trademark.</p>

<!-- Single comment nested inside element -->
<h1 gettext="."><!-- TRANSLATORS: Main page heading -->Welcome</h1>

<!-- Multiple comments nested inside element -->
<p gettext=".">
  <!-- TRANSLATORS: This is the introduction paragraph -->
  <!-- TRANSLATORS: Keep the tone professional but friendly -->
  Welcome to our service.
</p>

<!-- Mixed: comments before and inside (both will be extracted) -->
<!-- TRANSLATORS: This is important -->
<span gettext="."><!-- TRANSLATORS: Also this -->Important text</span>

<!-- Inherited comments from ancestor -->
<!-- TRANSLATORS: Legal disclaimer applies to all content below -->
<article>
  <h1 gettext=".">Title</h1>
  <p gettext=".">Content with inherited disclaimer</p>
</article>

<!-- Complex: all three types combined -->
<!-- TRANSLATORS: Site-wide legal notice -->
<body>
  <!-- TRANSLATORS: Header-specific instruction -->
  <header>
    <h1 gettext=".">
      <!-- TRANSLATORS: Nested comment for this specific heading -->
      Welcome
    </h1>
  </header>
</body>
```

## Comment Extraction Order

Comments are extracted in this order:
1. Auto-generated location comment (always included)
2. Adjacent context (from `gettext-context-adjacent` attribute)
3. SOURCE comment (auto-generated file reference)
4. Nested comments (inside the @gettext element, before any text/non-comment nodes)
5. Preceding comments from the element itself and all ancestors (walked via `ancestor-or-self::*`)

All applicable comments are concatenated in the `.po` file. Duplicate comments are automatically deduplicated.

# Nested Element Translation

The `gettext="."` attribute works on elements containing **other elements** too. Child elements become numbered placeholders in the `.po` file:

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

# Translation Workflow

To extract the translatable strings from the HTML file, you need to run the `bin/zolinga gettext:extract [--domains={DOMAINS}]` command.

```bash
bin/zolinga gettext:extract --domains=MyModule
```

**Note**: This command modifies source HTML files in place by adding `#HASH` suffixes to `gettext` attributes. Commit the updated source files after extraction.

This will generate `{MODULE}/locale/{language}_{TERRIRORY}.po` files with the translatable strings. You need to translate the strings in these files and
then run the `bin/zolinga gettext:compile [--domains={DOMAINS}]` command to generate the translated HTML files. After running the compilation command,
the translated HTML files will be created with `*.{langugage}-{TERRITORY}.html` suffix in the same directory as source HTML files.

Example:

```
📁 MyModule
    📁 locale
        📄 cs_CZ.po // contains strings for translation
        📄 fr_FR.po // contains strings for translation
    📁 install
        📁 dist
            📄 index.html // source file with <meta name="gettext" content="translate"/>
            📄 index.cs-CZ.html // auto-generated translation 
            📄 index.fr-FR.html // auto-generated translation
```

# Maintaining and Updating Translations

When the translated `*.{langugage}-{TERRITORY}.html` files are generated they have `<meta name="gettext" content="replace"/>` element in the `<head>` section. This element tells system to remove the file and generate a new one with the latest translations. This means that you should not do any manual changes to the translated files, because they will be overwritten by the next compilation.

If you need to manually maintain the translated file, you should remove the `<meta name="gettext" content="replace"/>` element from the `<head>` section. This will prevent the file from being overwritten by the next compilation. It also means that you will need to manually update the file with the latest translations.

To get the best of both worlds you can change the meta tag in the translated file to `<meta name="gettext" content="cherry-pick"/>`. This will prevent the file from being overwritten by the next compilation and only the strings marked with attribute `gettext` will be updated. Everything else will remain untouched. This is usefull when you need to maintain large translated 
article without gettext while still being able to update some parts automatically.

Example of the translated file `index.cs-CZ.html` with `<meta name="gettext" content="cherry-pick"/>`:


```html
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="gettext" content="cherry-pick"/>
    <title gettext=".#A32H52">Moje HTML stránka</title>
    <meta name="description" content="Náš obchod." gettext="content#B34A32"/>
  </head>
  <body>
    <h1>Podmínky užívání služby</h1>
    <p>Tyto podmínky užívání služby jsou platné od 1.1.2024...</p>
    <p>Prodávající si vyhrazuje právo na změnu podmínek užívání služby...</p>
    <p>Reklamace zboží je možná do 14 dnů od zakoupení...</p>
  </body>
</html>
```

In this example, the `<title>` and `<meta name="description">` elements will be updated with the latest translations, but the rest of HTML including `<h1>` and `<p>` elements will remain untouched solely in the discretion of the translator.

Note that the `gettext` attributes in the translated file have `#HASH` suffixes. These are used to identify the original `msgid` strings in the `.po` files. If you need to add new translatable string into cherry-picked file, just add the untranslated English version of it and mark it with `gettext` attribute as usual. On next compilation it will be translated using `.po` files and the `#HASH` suffix will be added to it.

### Hash Suffixes in Source Files

When you run `bin/zolinga gettext:extract`, **source HTML files are modified in place**: each keyword in every `gettext` attribute receives a `#`-prefixed 6-character hash suffix. These hashes uniquely identify each translatable element and serve as stable links between source and translated files. The compiler uses matching hashes to find the corresponding `msgid` in `.po` files and to update translated elements correctly.

Before extraction:
```html
<h1 gettext=".">Hello</h1>
<p gettext=". title" title="Welcome!">Hello!</p>
```

After extraction:
```html
<h1 gettext=".#a3f2b1">Hello</h1>
<p gettext=".#d2bc00 title#1396ff" title="Welcome!">Hello!</p>
```

**Important**: Since extraction modifies source files, commit the updated files after running `gettext:extract`. 

_Warning_: If you modify the master HTML file, the cherry-picked HTML files will not be updated automatically. E.g. if you add CSS styles or images, they will not be added to the translated files. You will need to manually update the translated files. The `<meta name="gettext" content="replace"/>` is the best option for most cases as it regenerates the whole file and keeps it up to date with the master file.

# Testing

For testing the translation workflow without affecting production domains, use the `test` domain:

```bash
bin/zolinga gettext:extract --domains=test
bin/zolinga gettext:compile --domains=test
```

The `test` domain scans files and creates output in `data/zolinga-intl/gettext-test/` folder. This is ideal for testing behavior and experimenting with the translation pipeline.

# Web Components Support

If you want to load localized HTML layouts when using `/dist/system/js/web-component.js` [Web Component](:Zolinga Core:Web Components:WebComponent Class)'s `loadContent()` methods in you JS files, you can use the `/dist/zolinga-intl/js/web-component-intl.js` module instead. It extends the `WebComponent` class with the ability to load localized HTML files automatically.