<?php

declare(strict_types=1);

/*
 * Simulates one web request in a process of its own, with no catalogue loaded
 * and the gettext filters of the scenario attached, as
 * tests/Fixtures/wordpress-functions.php stands in for WordPress.
 *
 * Usage: php filters.php <json>
 * The JSON holds 'wordpress' (false: no WordPress function at all), 'filters'
 * (hook => suffix its callback appends), 'plain' (a text comes back as written
 * when no filter changes it) and 'steps': one list of [domain, context] per
 * call of Translator::resolve(), one Translatable in it for each pair.
 * Prints each result, in order, and the texts __() and _x() were asked.
 */

use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\Translator;

require dirname(__DIR__, 2).'/vendor/autoload.php';

/** @var array{wordpress: bool, filters: array<string, string>, plain: bool, steps: list<list<array{string, string|null}>>} $scenario */
$scenario = json_decode((string) ($argv[1] ?? ''), true, flags: JSON_THROW_ON_ERROR);

if ($scenario['wordpress']) {
    require __DIR__.'/wordpress-functions.php';

    $GLOBALS['i18n_wordpress'] = ['filters' => $scenario['filters'], 'plain' => $scenario['plain'], 'calls' => 0, 'asked' => []];
}

$results = [];

foreach ($scenario['steps'] as $step) {
    $arguments = [];

    foreach ($step as $index => [$domain, $context]) {
        $arguments['text'.$index] = new Translatable('Book', $domain, $context);
    }

    foreach (Translator::resolve($arguments, 'pollora') as $text) {
        $results[] = $text;
    }
}

echo json_encode(['results' => $results, 'asked' => $GLOBALS['i18n_wordpress']['asked'] ?? []], JSON_THROW_ON_ERROR);
