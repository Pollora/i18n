<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\AttributeSource;
use Pollora\I18n\Tooling\Console\ExitCode;
use Pollora\I18n\Tooling\PhpParserMissingException;

/**
 * Runs a script in a process where nikic/php-parser cannot be autoloaded.
 *
 * @param  list<string>  $arguments
 * @return array{code: int, out: string, err: string}
 */
function withoutPhpParser(string $script, array $arguments = [], ?string $directory = null): array
{
    $command = [PHP_BINARY, '-d', 'display_errors=stderr', '-d', 'auto_prepend_file='.__DIR__.'/../Fixtures/without-php-parser.php', $script, ...$arguments];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);

    if (! is_resource($process)) {
        throw new RuntimeException('The process could not start.');
    }

    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'out' => $out, 'err' => $err];
}

it('names the missing library, and installs nothing, when php-parser is absent', function (): void {
    $run = withoutPhpParser(__DIR__.'/../Fixtures/extract-without-php-parser.php');
    $result = json_decode($run['out'], true, flags: JSON_THROW_ON_ERROR);

    expect($run['code'])->toBe(0)
        ->and($result['caught'][0])->toBe(PhpParserMissingException::class)
        ->and($result['caught'][1])->toContain('composer require --dev nikic/php-parser')
        ->and($result['caught'][2])->toBeTrue()
        ->and($result['parserLoaded'])->toBeFalse();
});

it('makes the binary print the installation command and exit with 1 when php-parser is absent', function (): void {
    $project = cliProject();
    $run = withoutPhpParser(dirname(__DIR__, 2).'/bin/pollora-i18n', ['extract', '--domain=books', '--path=src', '--declarations=declarations.php', '--cache=build/cache.php'], $project);

    expect($run['code'])->toBe(ExitCode::FAILURE)
        ->and($run['err'])->toContain('composer require --dev nikic/php-parser')
        ->and($run['err'])->not->toContain('Fatal error')
        ->and($run['out'])->toBe('');
});

it('tells how to install php-parser in its message', function (): void {
    $exception = PhpParserMissingException::notInstalled();

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toContain('composer require --dev nikic/php-parser');
});

it('refuses to extract before reading any file when the parser class is not there', function (): void {
    $source = new AttributeSource(null, null, 'PhpParser\\NotInstalled');

    expect(static fn () => $source->extract([__DIR__.'/does-not-exist'], 'books'))->toThrow(PhpParserMissingException::class);
});
