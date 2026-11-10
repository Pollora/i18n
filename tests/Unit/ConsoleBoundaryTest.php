<?php

declare(strict_types=1);

it('keeps Symfony inside Tooling\Console, so the services run without symfony/console', function (): void {
    $offenders = [];

    foreach (glob(dirname(__DIR__, 2).'/src/Tooling/*.php') ?: [] as $file) {
        if (str_contains((string) file_get_contents($file), 'Symfony')) {
            $offenders[] = basename($file);
        }
    }

    expect($offenders)->toBe([])
        ->and(glob(dirname(__DIR__, 2).'/src/Tooling/Console/*.php'))->not->toBeEmpty();
});

it('runs the services in a process that cannot load symfony/console', function (): void {
    $script = <<<'PHP'
<?php
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Symfony\\')) {
        fwrite(STDERR, "loaded $class\n");
        exit(9);
    }
});
require $argv[1].'/vendor/autoload.php';
$languages = $argv[2];
(new Pollora\I18n\Tooling\CatalogUpdater)->update('books', $languages.'/books.pot', $languages, ['fr_FR']);
(new Pollora\I18n\Tooling\CatalogCompiler)->compile($languages);
echo 'ok';
PHP;
    $languages = cliDirectory();
    cliWrite($languages, ['books.pot' => "msgid \"\"\nmsgstr \"\"\n\nmsgid \"Book\"\nmsgstr \"\"\n"]);

    file_put_contents($languages.'/run.php', $script);
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', $languages.'/run.php', dirname(__DIR__, 2), $languages], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);

    expect(proc_close($process))->toBe(0, $err)->and($out)->toBe('ok');
});
