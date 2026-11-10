<p align="center">
  <a href="https://packagist.org/packages/pollora/i18n"><img src="https://img.shields.io/packagist/v/pollora/i18n" alt="Latest version"></a>
  <a href="https://packagist.org/packages/pollora/i18n"><img src="https://img.shields.io/packagist/dt/pollora/i18n" alt="Total downloads"></a>
  <a href="https://github.com/Pollora/i18n/actions/workflows/harness.yml"><img src="https://github.com/Pollora/i18n/actions/workflows/harness.yml/badge.svg" alt="Harness"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/i18n" alt="License"></a>
</p>

Translatable labels for WordPress code that declares them as constants, such as the arguments of a PHP attribute, and the tooling to extract them into a catalogue. It runs with or without Laravel.

> Part of [Pollora](https://pollora.dev), the Laravel framework for WordPress. It is an early skeleton: the runtime and the first catalogue object exist, the extraction does not yet.

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

## License

MIT, see [LICENSE](LICENSE).
