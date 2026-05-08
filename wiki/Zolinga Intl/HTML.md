# Overview

You may choose to generate static translations of your static HTML resources. This is very useful for more complex HTML resources with a lot of text.

To do this, you can need to mark the HTML file for translation and then run the `bin/zolinga gettext:extract` command to extract the translatable strings from the HTML file.
Translate the strings and then run the `bin/zolinga gettext:compile` command to generate translated HTML files.

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

Note that the `gettext` attributes in the translated file have `\#HASH` suffixes. These are used to identify the original `msgid` strings in the `.po` files. If you need to add new translatable string into cherry-picked file, just add the untranslated English version of it and mark it with `gettext` attribute as usual. On next compilation it will be translated using `.po` files and the `\#HASH` suffix will be added to it. 

_Warning_: If you modify the master HTML file, the cherry-picked HTML files will not be updated automatically. E.g. if you add CSS styles or images, they will not be added to the translated files. You will need to manually update the translated files. The `<meta name="gettext" content="replace"/>` is the best option for most cases as it regenerates the whole file and keeps it up to date with the master file.

# Web Components Support

If you want to load localized HTML layouts when using `/dist/system/js/web-component.js` [Web Component](:Zolinga Core:Web Components:WebComponent Class)'s `loadContent()` methods in you JS files, you can use the `/dist/zolinga-intl/js/web-component-intl.js` module instead. It extends the `WebComponent` class with the ability to load localized HTML files automatically.