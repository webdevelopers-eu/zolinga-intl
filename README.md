**This repository holds the  [Zolinga](https://github.com/webdevelopers-eu/zolinga) module.**

Please refer to [Zolinga PHP Framework](https://github.com/webdevelopers-eu/zolinga) for more information. Also refer to inbuilt [Zolinga WIKI](https://github.com/webdevelopers-eu/zolinga/blob/main/system/data/zolinga-wiki-screenshot.png) that comes with the your Zolinga framework installation for most up-to-date information.

# Zolinga Internationalization

Zolinga is focused on multilingual support. This means that the system is designed to support multiple languages and locales. This is achieved by using the [gettext](https://www.gnu.org/software/gettext/) library.

## Installation

First you need to install the Zolinga framework. See [Zolinga PHP Framework](https://github.com/webdevelopers-eu/zolinga). Then you can install the Zolinga CMS module by running this command in the root of your Zolinga installation:

```bash
$ ./bin/zolinga install --module=zolinga-cms
```

## Terminology

- **Locale**
  - A set of parameters that defines the user's language, region, and any special variant preferences that the user wants to see in their user interface. A locale name is usually in the form `language_TERRITORY`, where `language` is an ISO 639 language code, and `TERRITORY` is an ISO 3166 country code. For example, `en_US` is the locale for US English, and `fr_FR` is the locale for French in France.
- **msgid**
  - A string identifier. This is a unique identifier for a string that needs to be translated. It is used to look up the translated string in the translation files. The `strid` is usually the English version of the string. For example, the `strid` for the string "Hello" is "Hello".
- **msgstr**
  - The translated string. This is the translated version of the `msgid`. For example, the `msgstr` for the string "Hello" in French is "Bonjour".
- **domain**
  - Each domain groups a set of related translations. In Zolinga, the domain is the name of the module that the translation is for. For example, the domain for the `zolinga-intl` module is `zolinga-intl`.
- **PO file**
  - A Portable Object file. This is a file that contains the translations for a domain. It is a text file that contains the `msgid` and `msgstr` pairs for a domain. The file has a `.po` extension and is located in folder `locale` in the module's directory.
- **MO file**
  - A Machine Object file. This is a binary file that contains the translations for a domain. It is a compiled version of the PO file. The file has a `.mo` extension and is located in folder `locale` in the module's directory.

## What Does Module Zolinga-Intl Provide?

- `$api->locale` 
  - service to get and set the current locale and more.
- **Dynamic PHP** 
  - script translation using the `gettext` library. You can use [dgettext](https://www.php.net/manual/en/function.dgettext.php) or [ngettext](https://www.php.net/manual/en/function.ngettext.php) functions anywhere in your PHP code. Read more here (see Zolinga inbuilt WIKI).
  - Example: `echo dgettext('zolinga-intl', 'Hello');`
- **Dynamic JavaScript** 
  - translation using the javascript version of the `gettext` library. You can use the `gettext` and `ngettext` functions in your JavaScript code. Read more in Zolinga inbuilt WIKI.
  - Example: `console.log(gettext('Hello'));`
- **Static HTML files**
  - translation using the `gettext` library. You can add the `gettext` attribute to any HTML tag to translate the text inside the tag or any of its attributes. System will automatically pre-generate static translated HTML files. Read more in Zolinga inbuilt WIKI.
  - Example: `<h1 gettext=". title" title="Welcome!">Hello!</h1>`

# Translation Workflow

The translation workflow consists of the following steps

- **Marking the translatable strings**
  - You need to mark the translatable strings in your code. For details refer to the specific section for the type of translation you want to use.
    * translating HTML (see Zolinga inbuilt WIKI) static files
    * translating PHP (see Zolinga inbuilt WIKI) dynamic files
    * translating JavaScript (see Zolinga inbuilt WIKI) dynamic files
- **Extracting the translatable strings**
  - You need to extract the translatable strings from your code. This will generate a `.po` files by scanning your code. The scanning and extraction is done by the `bin/zolinga gettext:extract --module={MODULE}` command. The result are `.po` files in the `locale` directory of the module.
- **Translating the strings**
  - You need to translate untranslated strings in the `{MODULE}/locale/*.po` files. You can use any text editor or a specialized translation tool like [Poedit](https://poedit.net/).
- **Compiling the translations**
  - You need to compile the translations to generate the `.mo` files for PHP, `.json` dictionaries for Javascript or `.{lang}_{TERRITORY}.html` static translations for HTML. The compilation is done by the `bin/zolinga gettext:compile --module={MODULE}` command.

# Languages

The list of languages is configured in Zolinga's file `{ROOT}/config/global.json`.

Example:

```json
{
  "intl": {
    "locales": [
      "en_US",
      "cs-CZ"
    ]
  }
}
```