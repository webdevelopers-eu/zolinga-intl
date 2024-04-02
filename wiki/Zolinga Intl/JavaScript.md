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

# Preparing Dictionaries

The standard workflow for preparing dictionaries is the same as for PHP. System will automatically prepare binary `.mo` files for PHP and `.json` dictionaries for JavaScript. You can use the `bin/zolinga gettext:extract --module={MODULE}` command to extract all strings from the module and create a `{MODULE}/locale/{lang}_{TERRITORY}.po` file for each language. These plain text files are intended to be translated by a human translator. 

When a translator finishes translating the dictionary, you need to compile it into a machine-readable format. This is done by running `bin/zolinga gettext:compile --module={MODULE}`. This command will compile all `.po` files into binary `.json` files for JavaScript.

