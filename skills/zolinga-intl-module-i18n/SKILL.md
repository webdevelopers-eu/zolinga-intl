---
name: zolinga-intl-module-i18n
description: Use when you are asked to localize certain module or contents.
argument-hint: "<locale>"
---

# Preparing Module

- Create `./locale` directory in your module.
- If the module has localizable javascript files in `./install/dist` folder, create `./install/dist/locale` directory as well.
- Make sure the resulting config file merged from `./config/` (see documentation about configuration files) has all the supported languages set correctly. Those will be used to generate language-specific resource files in the next step.
- Run `zolinga gettext:extract` to extract translatable strings from the module and generate language-specific resource files in `./locale` and `./install/dist/locale` directories. You can add `--module=<module-name>` option to extract strings only from a specific module. Without that it will extract strings from all modules.
  - System will scan each module and will create a resource files in each create `locale` directory. The javascript-locale files in `./install/dist/locale` will have only the `./install/dist/locale/messages.pot` file that will be merged into module's `locale/messages.pot` file. User is supposed to edit only `./locale/<lang>.po` files.
  - on compilation phase the data will be extracted from `./locale/` and `./install/dist/locale/<lang>.json` files will be automatically created - no need to edit anything inside `./install/dist/locale/` directory manually. 
