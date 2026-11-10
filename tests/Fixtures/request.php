<?php

declare(strict_types=1);

/*
 * Simulates one web request in a process of its own, so that the functions
 * WordPress would define exist, or not, for this request only.
 *
 * Usage: php request.php <wordpress|none|resolve-none>
 * Prints a JSON object: the translated string and the Tooling classes loaded.
 */

use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Runtime\Translator;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (($argv[1] ?? 'none') === 'wordpress') {
    require __DIR__.'/wordpress-functions.php';
}

if (($argv[1] ?? '') === 'resolve-none') {
    $resolved = Translator::resolve(['a' => ['b' => new Translatable('Written by', 'own')], 'c' => [new Translatable('Book', null, 'noun')], 'd' => new Translatable('Écrit par')], 'pollora');

    echo json_encode(['result' => $resolved['a']['b'].'|'.$resolved['c'][0].'|'.$resolved['d'], 'tooling' => []], JSON_THROW_ON_ERROR);

    return;
}

TranslatableRegistry::register(stdClass::class, ['singular']);

$result = Translator::translate(stdClass::class, 'singular', 'Book', 'pollora');

$tooling = array_values(array_filter(
    [...get_declared_classes(), ...get_declared_interfaces()],
    static fn (string $class): bool => str_starts_with($class, 'Pollora\\I18n\\Tooling\\'),
));

echo json_encode(['result' => $result, 'tooling' => $tooling], JSON_THROW_ON_ERROR);
