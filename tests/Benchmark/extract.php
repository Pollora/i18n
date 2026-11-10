<?php

declare(strict_types=1);

/*
 * Cost of AttributeSource::extract() on 300 files with a marked text and 300 without, cold (no cache) then warm
 * (cache of unchanged files), and cost of the pre-filter alone on the files without one.
 * The same file measures an older tree: pass its root as the first argument; the plural case is skipped when
 * TranslatablePlural does not exist there.
 *
 * Usage: XDEBUG_MODE=off php tests/Benchmark/extract.php [root] [series]
 * Not part of the test suite. Prints the median of the series, in milliseconds.
 */

use Pollora\I18n\Tooling\AttributeSource;

$root = $argv[1] ?? dirname(__DIR__, 2);
$series = (int) ($argv[2] ?? 7);

require $root.'/vendor/autoload.php';

/**
 * @param  list<float>  $values
 */
function benchmarkExtractMedian(array $values): float
{
    sort($values);

    return $values[intdiv(count($values), 2)];
}

/**
 * Writes 300 files with a marked text and 300 that mention an attribute and nothing else.
 */
function benchmarkExtractFiles(string $directory, string $marker): void
{
    mkdir($directory);
    $with = "<?php\nnamespace N%1\$d;\nuse Pollora\\I18n\\Runtime\\".$marker." as T;\n#[Cols(['a' => new T(%2\$s)])]\nclass C%1\$d {}\n";
    $without = "<?php\nnamespace G%1\$d;\n#[Deprecated]\nclass G%1\$d\n{\n".str_repeat("    public function run(): void\n    {\n        \$this->value = strtoupper('text');\n    }\n\n", 30)."}\n";

    for ($index = 0; $index < 300; $index++) {
        file_put_contents($directory.'/with'.$index.'.php', sprintf($with, $index, $marker === 'Translatable' ? "'String ".$index."'" : "'One ".$index."', '%d of ".$index."', comment: 'Note'"));
        file_put_contents($directory.'/without'.$index.'.php', sprintf($without, $index));
    }
}

function benchmarkExtractRemove(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($directory);
}

$markers = ['Translatable'];

if (class_exists('Pollora\\I18n\\Runtime\\TranslatablePlural')) {
    $markers[] = 'TranslatablePlural';
}

printf("PHP %s, 300 files with one marker and 300 without, median of %d series, ms\n", PHP_VERSION, $series);

foreach ($markers as $marker) {
    $directory = sys_get_temp_dir().'/pollora-i18n-bench-'.bin2hex(random_bytes(4));
    benchmarkExtractFiles($directory, $marker);
    $cold = [];
    $warm = [];

    for ($run = 0; $run < $series; $run++) {
        $cache = $directory.'/cache-'.$run.'.php';
        $source = new AttributeSource($cache);
        $start = hrtime(true);
        $source->extract([$directory], 'default');
        $cold[] = (hrtime(true) - $start) / 1e6;
        $start = hrtime(true);
        $source->extract([$directory], 'default');
        $warm[] = (hrtime(true) - $start) / 1e6;
        unlink($cache);
    }

    printf("%-20s cold %7.1f   warm %7.1f\n", $marker, benchmarkExtractMedian($cold), benchmarkExtractMedian($warm));
    benchmarkExtractRemove($directory);
}

$sample = str_repeat("    public function run(): void\n    {\n        \$this->value = strtoupper('text');\n    }\n\n", 30);
$contents = array_map(static fn (int $index): string => "<?php\nnamespace G".$index."\n#[Deprecated]\nclass G\n{\n".$sample."}\n", range(1, 300));
$patterns = ['/\btranslatable\b/i', '/\btranslatable(?:plural)?\b/i'];

foreach ($patterns as $pattern) {
    $times = [];

    for ($run = 0; $run < $series; $run++) {
        $start = hrtime(true);

        for ($repeat = 0; $repeat < 20; $repeat++) {
            foreach ($contents as $text) {
                preg_match($pattern, $text);
            }
        }

        $times[] = (hrtime(true) - $start) / 1e6 / 20;
    }

    printf("pre-filter %-34s 300 files without a marker: %6.2f ms\n", $pattern, benchmarkExtractMedian($times));
}
