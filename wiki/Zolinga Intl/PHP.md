# Overview

Zolinga is focused on multilingual support. This means that the system is designed to support multiple languages and locales. This is achieved by using the [gettext](https://www.gnu.org/software/gettext/) library.

To translate a string, you need to use the `dgettext` or `dngettext` function. The `dgettext` function is used to translate a single string, while the `dngettext` function is used to translate a string that has a plural form.

Syntax:

```php
dgettext(string $domain, string $message): string
dngettext(string $domain, string $singular, string $plural, int $count): string
```
- `$domain` - This your module (folder) name. The domain is used to separate translations for different modules.
- `$message` - The string to translate.
- `$singular` - The singular form of the string to translate.
- `$plural` - The plural form of the string to translate.
- `$count` - The number of items to translate. This is used to determine which form of the string to use - singular or plural.


You may use context separator constant: `GETTEXT_CTX_END` (`"\x04"`) to separate the context from the message. This is useful when you have multiple translations for the same string in different contexts.

Example:

```php
echo dgettext('my-module', 'Welcome message' . GETTEXT_CTX_END . 'Hello, world!');
```

The `dgettext` function will look for the translation of the string `Hello, world!` in the prepared dictionary for `my-module` domain and return the translated string. 

```php
echo sprintf(dngettext('my-module', 'There is one apple', 'There are %d apples', 3), 3);
```

Firxt the inner `dngettext` function will be executed. It will find the translations for the English text and based on the fourth parameter, it will return the plural form of the translated string. The returned plural form will passed to the `sprintf` function to replace the optional `%d` with the number `3`.

# Preparing Dictionaries

To make the translation work, you need to prepare a dictionary for each language. The dictionary is a file that contains translations for all strings used in your module. The dictionary is created by running `bin/zolinga gettext:extract --module={MODULE}` . This command will extract all strings from the module and create a `{MODULE}/locale/{lang}_{TERRITORY}.po` file for each language. These plain text files are intended to be translated by a human translator. You can use any text editor to edit these files or use a specialized tool like [Poedit](https://poedit.net/).

When a translator finishes translating the dictionary, you need to compile it into a machine-readable format. This is done by running `bin/zolinga gettext:compile --module={MODULE}`. This command will compile all `.po` files into binary `.mo` files for faster access and PHP will use these files to translate strings.
