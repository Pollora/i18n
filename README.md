<p align="center">
  <a href="https://packagist.org/packages/pollora/i18n"><img src="https://img.shields.io/packagist/v/pollora/i18n" alt="Latest version"></a>
  <a href="https://packagist.org/packages/pollora/i18n"><img src="https://img.shields.io/packagist/dt/pollora/i18n" alt="Total downloads"></a>
  <a href="https://github.com/Pollora/i18n/actions/workflows/harness.yml"><img src="https://github.com/Pollora/i18n/actions/workflows/harness.yml/badge.svg" alt="Harness"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/i18n" alt="License"></a>
</p>

Translatable labels for WordPress code that declares them as constants, such as the arguments of a PHP attribute, and the tooling to extract them into a catalogue. It runs with or without Laravel.

> Part of [Pollora](https://pollora.dev), the Laravel framework for WordPress. It is an early release: the runtime, the catalogue formats, the extraction of attributes and the `pollora-i18n` binary exist.

## Installation

```bash
composer require pollora/i18n
```

Requires PHP 8.3+. Nothing else is required: the libraries the tooling uses (`nikic/php-parser`, `symfony/console`, `illuminate/view`) are suggested, and installed only where you run the console.

## Two halves, one cost

| Namespace | Loaded | Does |
| --- | --- | --- |
| `Pollora\I18n\Runtime` | on every request | Declares which fields are translatable and translates them |
| `Pollora\I18n\Tooling` | in the console only | Builds the catalogue |

A web request loads `TranslatableRegistry` and `Translator` and nothing from `Tooling`; a test checks it, and so does the architecture suite.

## Runtime

```php
use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Runtime\Translator;

TranslatableRegistry::register(PostType::class, ['singular', 'plural']);

$label = Translator::translate(PostType::class, 'singular', 'Book', 'my-domain');
```

`translate()` returns the string unchanged when WordPress is not loaded, when the field is not declared translatable, or when the site runs in `en_US` with no catalogue loaded for the domain. Otherwise it makes one call to `__()`. It uses no reflection and reads no file.

## Text in an array of arguments

An attribute parameter that is an array mixes text people read with technical values, so no list of fields can say which is which. Mark the text where you write it:

```php
use Pollora\I18n\Runtime\Translatable as T;

#[AdminColumns(['author' => new T('Written by'), 'rating' => new T('Rating', context: 'stars')])]
final class Book {}
```

`new T('...', domain: 'my-domain', context: '...')` is allowed in an attribute argument. Cast to a string it gives its text as written, and the extraction finds it. The code that builds the registration calls `Translator::resolve($arguments, 'my-domain')` once: it walks the arrays (16 levels at most), replaces each `Translatable` by `__()` or, with a context, `_x()`, and leaves everything else alone. A value that is not an array comes back at once, and an array with no `Translatable` comes back unchanged, uncopied.

A parameter typed `string` refuses the object (a `TypeError`): use `Translatable` where the parameter is an `array`, `mixed` or `string|Stringable`.

`new T('...', comment: '...')` adds a note for the translators (`#.` in the template). It is ignored when the text is resolved. A context is `null` for none: an empty string is refused, with an `InvalidTranslatableException`.

### Plural texts

A text that depends on a number is declared with both forms, and translated later, when the count is known:

```php
use Pollora\I18n\Runtime\TranslatablePlural as P;

#[AdminColumns(['books' => new P('One book', '%d books', context: 'library', comment: 'Shown in the column header')])]
final class Author {}
```

`Translator::resolve()` replaces each `TranslatablePlural` by the structure of WordPress's `_n_noop()` (`_nx_noop()` with a context), never by a string, whatever the locale. The code that displays the text gives it the count:

```php
$noop = $columns['books'];

printf(translate_nooped_plural($noop, $count), number_format_i18n($count));
```

The domain is the one of the `TranslatablePlural`, else the one given to `resolve()`. When WordPress is not loaded, `resolve()` builds the same array (same keys, same order) by hand. `sprintf` replacements stay in the code that displays the text: the translated string is the template (`%s`, `%1$d`), never the result. `TranslatablePlural` cast to a string gives its singular form as written; an empty singular, plural or context is refused with an `InvalidTranslatablePluralException`.

## The command line

`vendor/bin/pollora-i18n` runs the whole chain without Laravel: extract the strings, update the translated files, compile them for WordPress. It needs `symfony/console` (`composer require --dev symfony/console`); the web request never loads it.

```bash
vendor/bin/pollora-i18n extract --domain=my-theme --path=app --path=resources --cache=build/i18n.php
vendor/bin/pollora-i18n update  --domain=my-theme --locale=fr_FR
vendor/bin/pollora-i18n compile --domain=my-theme
```

```text
created   languages/my-theme.pot
1 created, 0 updated, 0 unchanged
```

Each command prints one line per file (`created`, `updated` or `unchanged`) and a summary. A file is written only when its bytes change, so a second run writes nothing and leaves every modification time alone. `--dry-run` writes nothing and prints the same lines and figures, `--quiet` prints nothing (the exit code still tells), `-v` adds how many files were parsed.

| Command | Does | Options |
| --- | --- | --- |
| `extract` | Builds `languages/{domain}.pot` from the translatable attributes of the given folders | `--domain` and `--path` (repeatable) are required. `--output`, `--cache` (parse only the files that changed), `--merge` (repeatable: union a template from `wp i18n make-pot`, for JavaScript and `block.json`), `--installed` (default `vendor/composer/installed.json` when present), `--declarations` (repeatable: a file returning `[Class::class => ['field']]`), `--strict`, `--dry-run` |
| `update` | Merges the `.pot` into every `{domain}-{locale}.po` of the folder, keeping translations; strings that disappeared become obsolete | `--domain` is required. `--pot` (default `{languages}/{domain}.pot`), `--languages` (default `languages`), `--locale` (repeatable: create the file of a locale), `--dry-run` |
| `compile` | Writes `{domain}-{locale}.mo` and `.l10n.php` next to each `.po` file, with the translated, non-fuzzy, non-obsolete messages only | `--languages` (default `languages`), `--domain` (default: every `.po`), `--format=mo,php` (default both), `--force` (skip the "older than the `.po`" check), `--dry-run` |

The commands are also named `pollora:i18n:extract`, `pollora:i18n:update` and `pollora:i18n:compile`.

Exit codes: `0` done, `1` a file could not be read, parsed or written, `2` an option is missing or invalid (nothing was done), `3` `extract --strict` found a string it could not extract (the template is written all the same). Warnings (a non-literal argument, no attribute declared) go to the error output as `warning: file:line: message`.

The `.pot` has no date and sorted messages, and its references are relative to the current folder, so the same sources give the same file on every machine.

## License

MIT, see [LICENSE](LICENSE).
