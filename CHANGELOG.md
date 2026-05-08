# Changelog

All notable changes to the Zolinga Intl module.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0] — 2026-05-05
### Added
- `GettextDomain` — descriptor for gettext domains (name, folders, file types, output paths).
- `I18nService` (`$api->i18n`) — discover gettext domains via `getGettextDomains()`.
- HTML translation support: `.static.mo` files are now compiled for static HTML translations.
- `LocaleService::initGettext()` accepts an optional `$domainSuffix` parameter for binding `.static` domains.

### Changed
- `LocaleService::initGettext()` now uses the `default` domain for static translations and is idempotent.
- Static HTML translations should use `dgettext('<domain>.static', ...)` (e.g., `dgettext('default.static', ...)`).

### Removed
- `LINGUAS` file is no longer generated; locales are derived from supported locales and existing `.po` files.
