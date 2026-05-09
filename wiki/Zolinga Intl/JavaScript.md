# Overview

Zolinga is focused on multilingual support. This means that the system is designed to support multiple languages and locales. This is achieved by using the [gettext](https://www.gnu.org/software/gettext/) library.

You can use `gettext` and `ngettext` functions in your JavaScript code to translate strings. The `gettext` function is used to translate a single string, while the `ngettext` function is used to translate a string that has a plural form.

Syntax:

```javascript
import {gettext, ngettext} from "/dist/zolinga-intl/gettext.js?{MODULE}";

console.log("GETTEXT TEST", gettext("Hello, world!"));
console.log("NGETTEXT TEST", ngettext("One apple", "%s apples", 3, 3));
```

The `import` statment will import the `gettext` and `ngettext` functions initialized to translate strings using specified `{MODULE}` domain.

- `gettext` - This function is used to translate a single string.
- `ngettext` - This function is used to translate a string that has a plural form.

The syntax is as follows:

```javascript
gettext(string message): string
ngettext(string singular, string plural, int count, ..params): string
```

Note, that you don't need to use `dgettext` or `dngettext` functions requiring the `domain` parameter as you do in PHP. The `gettext` and `ngettext` functions are initialized to use the `{MODULE}` domain already.

## Translator Comments

You can add comments for translators by placing a JavaScript comment starting with `TRANSLATORS:` immediately before the gettext call. These comments are extracted and included in `.pot`/`.po` files to provide context and instructions:

```javascript
// TRANSLATORS: This is a call-to-action button label for the free trial signup
console.log(gettext('Start Your Free Trial'));

// TRANSLATORS: "Send" here refers to sending an email
console.log(gettext("Email transmission\x04Send"));
```

The comment must:
- Start with `TRANSLATORS:` (case-sensitive, singular form for PHP/JS)
- Be placed immediately before the gettext call
- Use standard JavaScript comment syntax (`//`)

Multiple comments can be used and will be concatenated in the `.po` file.

# Preparing Dictionaries

The standard workflow for preparing dictionaries is the same as for PHP. System will automatically prepare binary `.mo` files for PHP and `.json` dictionaries for JavaScript. You can use the `bin/zolinga gettext:extract --domains={DOMAINS}` command to extract all strings from the domain(s) and create a `{MODULE}/locale/{lang}_{TERRITORY}.po` file for each language. `--domains` accepts a comma-separated list of domains (for example `--domains=my-module,other-module`). These plain text files are intended to be translated by a human translator.

When a translator finishes translating the dictionary, you need to compile it into a machine-readable format. This is done by running `bin/zolinga gettext:compile --domains={DOMAINS}`. This command will compile all `.po` files into binary `.json` files for JavaScript.

