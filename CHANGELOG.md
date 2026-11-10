# Changelog

All notable changes to `pollora/i18n` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `Translator` takes WordPress for absent when `__()` or `_x()` is missing, not only `__()`. Laravel declares a `__()` of its own, with another signature: outside WordPress, `translate()` passed it a text domain where it expects an array, and a `Translatable` with a context called an `_x()` that does not exist, a fatal error. Both now give the text back as written. The check is still made once per call of `resolve()`, not once per text.
- `extract` and `AttributeSource` no longer stop with a fatal error when `nikic/php-parser`, which the package only suggests, is not installed: `AttributeSource::extract()` throws `PhpParserMissingException` (a `RuntimeException`) with the command to run (`composer require --dev nikic/php-parser`), before any `PhpParser` class is loaded, and the binary prints it and exits with 1.

## [0.1.0] - 2026-11-10

### Added

- `AttributeSource` extracts `new TranslatablePlural('One book', '%d books', 'domain', 'context', 'comment')` from the arguments of any attribute, at any depth, positional or named, in the same spellings as `Translatable`. The entry carries the singular form as `msgid` and the plural form as `msgid_plural`; the domain is the third argument, else the literal `textDomain` of the attribute, else the default domain, and the context is the fourth argument.
- The `comment` of a `Translatable` (fourth argument) and of a `TranslatablePlural` (fifth) becomes an extracted comment (`#.`) of the entry, placed before the provenance comment (`Attribute AdminCols › author.title`); both groups are sorted, so the order is stable. A comment that is not a literal string is reported and left out, the string is extracted all the same; an empty one is ignored.
- An empty context (`context: ''`) of a `Translatable` or a `TranslatablePlural` is reported with its file and line and the entry is not extracted, which is what happens at run time (the constructors refuse it).
- A string found several times is one entry with all its references and comments. When two occurrences disagree on the plural form, or one is a text and the other a plural, a conflict is reported with both references; the first occurrence in the order of the references is kept, whatever the order the files were read in.
- `tests/Benchmark/extract.php` measures `extract` cold and warm on 300 files with a marked text and 300 without, and the pre-filter alone.
- A test runs the same fixture class through the extraction and through `ReflectionAttribute::newInstance()`, `Translator::resolve()` and `translate_nooped_plural()` with WordPress functions that write down what they are asked, and requires the two sets of (message id, context, domain) to be identical. Another runs `bin/pollora-i18n extract`, `update` (a locale with three plural forms) and `compile`, and reads the results back.

- `TranslatablePlural`, a text with a singular and a plural form (`new TranslatablePlural('One book', '%d books', domain: ..., context: ..., comment: ...)`), and its `InvalidTranslatablePluralException` (empty form or context). `Translator::resolve()` replaces it, at any depth, by the structure of `_n_noop()` or, with a context, `_nx_noop()` (keys `0`, `1`, `2`, `singular`, `plural`, `context`, `domain`), for `translate_nooped_plural()`; it calls WordPress's functions when they exist and builds the same array by hand when they do not. The benchmark gains a case with three of them.
- `Translatable` takes a `comment` (translator note, ignored at run time) and refuses an empty context (`InvalidTranslatableException`); `null` still means no context.
- `Translatable`, a text marked where it is written inside an array argument (`new Translatable('Written by', domain: ..., context: ...)`), and `Translator::resolve()`, which replaces every `Translatable` of an array, at any depth up to 16 levels, by `__()` or `_x()`. It makes no call and no copy when there is nothing to resolve, and uses no reflection. `InvalidTranslatableException` (empty text) and `TranslatableTooDeepException` (circular reference) are its exceptions. `tests/Benchmark/resolve.php` measures it.
- `AttributeSource` extracts `new Translatable('Text', 'domain', 'context')` (`Pollora\I18n\Runtime\Translatable`) written in the arguments of any attribute, not only the declared ones, at any depth of nested arrays, positional or named. The class is recognised once names are resolved (aliases, full name, relative name, any case); another class named `Translatable` and a `new Translatable(...)` outside an attribute are left alone. The domain is the second argument, else the literal `textDomain` of the attribute, else the default domain; the third argument is the msgctxt (it wins over the context given to `extract()`). The comment is `Attribute AdminCols › author.title` (the keys of the arrays, no argument name). A text, domain or context that is not a literal string, and an empty text, give a `file:line` diagnostic. A `Translatable` inside a declared field is read by this rule, not reported as a non-literal.
- The pre-filter of `AttributeSource` lets a file through when it has `#[` and names a declared class or the word `Translatable`; a file with neither is still not parsed.
- The cache of `AttributeSource` holds these strings too, and a cache written by another version of the package is read as empty and rebuilt on the next run. `extract` does not warn that no attribute is declared when it found strings through `Translatable`.
- `bin/pollora-i18n`, a standalone binary with the `extract`, `update` and `compile` commands (also named `pollora:i18n:*`), built on `symfony/console`, which is a suggestion and a development dependency only. The commands live in `Pollora\I18n\Tooling\Console` and hold no logic: they call `CatalogExtractor`, `CatalogUpdater` and `CatalogCompiler`, which run without Symfony. Output is one line per file (`created`, `updated`, `unchanged`) with `--quiet` and `--dry-run`, and the exit codes are documented in the README.
- `Pollora\I18n\Runtime`, the part a web request loads: `TranslatableRegistry` (which fields of which class are translatable), `Translator` (one `__()` per string, nothing when it would change nothing) and the `ResolvedStringsStore` port.
- `Pollora\I18n\Tooling`, the part only the console loads: `CatalogEntry`, the value object of a catalogue entry (message id, context, plural, comments, references).
- `Catalog` and `CatalogMessage`, the immutable model of a `.pot` or `.po` file: headers, messages with their translations, flags and obsolete state.
- `PoWriter`, which writes a catalogue as the same text whatever the order of its messages, and `PoFileWriter::writeIfChanged()`, which replaces the file atomically and only when its content changes.
- `PoReader`, which reads a `.pot` or `.po` file line by line, loses nothing of what `PoWriter` writes, and raises a `PoParseException` naming the line of a malformed file.
