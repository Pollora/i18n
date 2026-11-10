<?php

declare(strict_types=1);

/*
 * Simulates one web request in a process of its own, so that the functions
 * WordPress would define exist, or not, for this request only.
 *
 * Usage: php request.php <wordpress|none|resolve-none|resolve-plural-none|resolve-plural-wordpress> [wordpress path]
 * Prints a JSON object: the translated string and the Tooling classes loaded.
 */

use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatablePlural;
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

if (($argv[1] ?? '') === 'resolve-plural-none') {
    $resolved = Translator::resolve(['plain' => new TranslatablePlural('One book', '%d books'), 'context' => new TranslatablePlural('One film', '%d films', 'own', 'cinema')], 'pollora');

    echo json_encode(['result' => json_encode($resolved, JSON_THROW_ON_ERROR), 'tooling' => []], JSON_THROW_ON_ERROR);

    return;
}

/*
 * The real WordPress functions: l10n.php is loaded as it is, with the few
 * things it needs around it. The structure the runtime gives must be the one
 * _n_noop() and _nx_noop() give, and translate_nooped_plural() must take it.
 */
if (($argv[1] ?? '') === 'resolve-plural-wordpress') {
    $path = $argv[2] ?? '';

    require_once $path.'/wp-includes/pomo/translations.php';
    require_once $path.'/wp-includes/l10n.php';

    /* Neither the hook system nor the textdomain registry is loaded: no filter, no catalogue to find. */
    function apply_filters(string $hook, mixed $value): mixed
    {
        return $value;
    }

    $wp_textdomain_registry = new class
    {
        public function has(string $domain): bool
        {
            return false;
        }
    };

    $plain = Translator::resolve(new TranslatablePlural('One book', '%d books'), 'pollora');
    $contextual = Translator::resolve(['x' => new TranslatablePlural('One film', '%d films', 'own', 'cinema')], 'pollora')['x'];

    $report = [
        'identical' => $plain === _n_noop('One book', '%d books', 'pollora') && $contextual === _nx_noop('One film', '%d films', 'cinema', 'own'),
        'plain' => [translate_nooped_plural($plain, 1), translate_nooped_plural($plain, 2), _n($plain['singular'], $plain['plural'], 1, 'pollora'), _n($plain['singular'], $plain['plural'], 2, 'pollora')],
        'context' => [translate_nooped_plural($contextual, 1), translate_nooped_plural($contextual, 2), _nx($contextual['singular'], $contextual['plural'], 1, $contextual['context'], 'own'), _nx($contextual['singular'], $contextual['plural'], 2, $contextual['context'], 'own')],
        'order' => array_keys($plain),
        'orderWithContext' => array_keys($contextual),
    ];

    echo json_encode(['result' => json_encode($report, JSON_THROW_ON_ERROR), 'tooling' => []], JSON_THROW_ON_ERROR);

    return;
}

TranslatableRegistry::register(stdClass::class, ['singular']);

$result = Translator::translate(stdClass::class, 'singular', 'Book', 'pollora');

$tooling = array_values(array_filter(
    [...get_declared_classes(), ...get_declared_interfaces()],
    static fn (string $class): bool => str_starts_with($class, 'Pollora\\I18n\\Tooling\\'),
));

echo json_encode(['result' => $result, 'tooling' => $tooling], JSON_THROW_ON_ERROR);
