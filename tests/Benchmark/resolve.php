<?php

declare(strict_types=1);

/*
 * Cost of Translator::resolve() against a naive array_walk_recursive.
 * Case (d) is skipped when TranslatablePlural does not exist, so the same file measures an older tree.
 *
 * Usage: XDEBUG_MODE=off php -n -d zend_extension=opcache -d opcache.enable_cli=1 tests/Benchmark/resolve.php [iterations] [series]
 * (a debugger or profiler extension multiplies every figure by two or three).
 * Not part of the test suite. Prints the median of the series, in nanoseconds per call.
 */

use Pollora\I18n\Runtime\Translatable;
use Pollora\I18n\Runtime\TranslatablePlural;
use Pollora\I18n\Runtime\Translator;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__).'/Fixtures/wordpress-functions.php';

$iterations = (int) ($argv[1] ?? 100000);
$series = (int) ($argv[2] ?? 7);

/**
 * @return array<string, mixed> 20 entries, nested over three levels
 */
function benchmarkArguments(bool $withTranslatable): array
{
    $text = static fn (string $text): string|Translatable => $withTranslatable ? new Translatable($text) : $text;
    $arguments = [];

    for ($index = 0; $index < 4; $index++) {
        $arguments['column_'.$index] = [
            'title' => $text('Title '.$index),
            'width' => 10 + $index,
            'sortable' => true,
            'options' => ['a' => 'x', 'b' => ['c' => 1, 'd' => null]],
        ];
    }

    $arguments['label'] = $text('Label');
    $arguments['hint'] = $text('Hint');

    return $arguments;
}

/**
 * The arguments of (a), with three plural texts in place of three plain ones.
 *
 * @return array<string, mixed>
 */
function benchmarkPluralArguments(): array
{
    $arguments = benchmarkArguments(false);
    $arguments['column_0']['title'] = new TranslatablePlural('One book', '%d books');
    $arguments['column_1']['count'] = new TranslatablePlural('One film', '%d films', 'own', 'cinema');
    $arguments['count'] = new TranslatablePlural('One author', '%d authors');

    return $arguments;
}

/**
 * @param  array<string, mixed>|string  $value
 * @return array<string, mixed>|string
 */
function benchmarkNaive(mixed $value, string $domain): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    array_walk_recursive($value, static function (mixed &$item) use ($domain): void {
        if ($item instanceof Translatable) {
            $item = $item->context === null ? __($item->text, $item->domain ?? $domain) : _x($item->text, $item->context, $item->domain ?? $domain);
        }
    });

    return $value;
}

function benchmarkMedian(callable $run, int $iterations, int $series): float
{
    $timings = [];

    for ($serie = 0; $serie < $series; $serie++) {
        $start = hrtime(true);

        for ($iteration = 0; $iteration < $iterations; $iteration++) {
            $run();
        }

        $timings[] = (hrtime(true) - $start) / $iterations;
    }

    sort($timings);

    return $timings[intdiv(count($timings), 2)];
}

$GLOBALS['i18n_wordpress'] = ['locale' => 'fr_FR', 'loaded' => [], 'calls' => 0];

$cases = [
    '(a) 20 entries, no Translatable' => benchmarkArguments(false),
    '(b) 20 entries, 6 Translatable' => benchmarkArguments(true),
    '(c) scalar' => 'Book',
];

if (class_exists(TranslatablePlural::class)) {
    $cases['(d) 20 entries, 3 TranslatablePlural'] = benchmarkPluralArguments();
}

printf("%d iterations, median of %d series, PHP %s, ns per call\n", $iterations, $series, PHP_VERSION);

foreach ($cases as $name => $value) {
    $empty = benchmarkMedian(static fn (): mixed => $value, $iterations, $series);
    $resolve = benchmarkMedian(static fn (): mixed => Translator::resolve($value, 'pollora'), $iterations, $series);
    $naive = str_starts_with($name, '(d)') ? null : benchmarkMedian(static fn (): mixed => benchmarkNaive($value, 'pollora'), $iterations, $series);

    printf("%-40s resolve %7.0f   naive %7s   (empty closure %4.0f)\n", $name, $resolve, $naive === null ? 'n/a' : sprintf('%.0f', $naive), $empty);
}
