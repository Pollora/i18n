<?php

declare(strict_types=1);

/*
 * One request in a process of its own, where the only __() is Laravel's (another signature) and WordPress's
 * _x(), _n_noop(), and the other WordPress functions do not exist.
 *
 * Prints a JSON object with what Translator::translate() and Translator::resolve() gave back.
 */

use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatablePlural;
use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Runtime\Translator;

require dirname(__DIR__, 2).'/vendor/autoload.php';

/**
 * @param  array<string, mixed>  $replace
 */
function __(string|array|null $key = null, array $replace = [], ?string $locale = null): string
{
    return 'laravel:'.$key;
}

TranslatableRegistry::register(stdClass::class, ['singular']);

$resolved = Translator::resolve([
    'plain' => new Translatable('Book'),
    'domain' => new Translatable('Film', 'own'),
    'context' => new Translatable('Author', null, 'person'),
    'scalar' => 'Untouched',
    'plural' => new TranslatablePlural('One book', '%d books'),
    'pluralContext' => new TranslatablePlural('One film', '%d films', 'own', 'cinema'),
], 'pollora');

echo json_encode([
    'translate' => Translator::translate(stdClass::class, 'singular', 'Book', 'pollora'),
    'text' => Translator::resolve(new Translatable('Book', null, 'noun'), 'pollora'),
    'resolved' => $resolved,
], JSON_THROW_ON_ERROR);
