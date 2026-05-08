# Changelog

All notable changes to the Zolinga Intl module.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0] — 2026-05-08
### Added
- `$api->i18n` service for gettext domain discovery via `getGettextDomains()`.
- New built-in `default` domain for translating site-wide files in `data/` and `public/` folders (not tied to any module).
- Static HTML translations now use a separate `.static.mo` dictionary, preventing pollution of the runtime `.mo` domain.
- HTML translation now supports nested elements — elements containing child translatable elements (e.g., `<p gettext=".">Click <a gettext=".">here</a></p>`) are properly handled using `<1>...</1>` placeholders in the `.po` file.

### Changed
- CLI commands `gettext:extract` and `gettext:compile` now accept `--domains` (comma-separated) instead of `--module`.
- `LocaleService::initGettext()` is now idempotent and accepts an optional `$domainSuffix` parameter for binding `.static` domains during compilation.

### Removed
- `LINGUAS` file is no longer generated; locales are derived from supported locales and existing `.po` files.
