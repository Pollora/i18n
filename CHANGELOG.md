# Changelog

All notable changes to `pollora/i18n` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Pollora\I18n\Runtime`, the part a web request loads: `TranslatableRegistry` (which fields of which class are translatable), `Translator` (one `__()` per string, nothing when it would change nothing) and the `ResolvedStringsStore` port.
- `Pollora\I18n\Tooling`, the part only the console loads: `CatalogEntry`, the value object of a catalogue entry (message id, context, plural, comments, references).
