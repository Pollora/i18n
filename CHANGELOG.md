# Changelog

All notable changes to `pollora/i18n` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `bin/pollora-i18n`, a standalone binary with the `extract`, `update` and `compile` commands (also named `pollora:i18n:*`), built on `symfony/console`, which is a suggestion and a development dependency only. The commands live in `Pollora\I18n\Tooling\Console` and hold no logic: they call `CatalogExtractor`, `CatalogUpdater` and `CatalogCompiler`, which run without Symfony. Output is one line per file (`created`, `updated`, `unchanged`) with `--quiet` and `--dry-run`, and the exit codes are documented in the README.
- `Pollora\I18n\Runtime`, the part a web request loads: `TranslatableRegistry` (which fields of which class are translatable), `Translator` (one `__()` per string, nothing when it would change nothing) and the `ResolvedStringsStore` port.
- `Pollora\I18n\Tooling`, the part only the console loads: `CatalogEntry`, the value object of a catalogue entry (message id, context, plural, comments, references).
- `Catalog` and `CatalogMessage`, the immutable model of a `.pot` or `.po` file: headers, messages with their translations, flags and obsolete state.
- `PoWriter`, which writes a catalogue as the same text whatever the order of its messages, and `PoFileWriter::writeIfChanged()`, which replaces the file atomically and only when its content changes.
- `PoReader`, which reads a `.pot` or `.po` file line by line, loses nothing of what `PoWriter` writes, and raises a `PoParseException` naming the line of a malformed file.
