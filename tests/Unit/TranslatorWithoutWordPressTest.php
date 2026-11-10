<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function laravelStyleRequest(): array
{
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', __DIR__.'/../Fixtures/laravel-request.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('The simulated request could not start.');
    }

    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException('The simulated request failed: '.$errors.$output);
    }

    /** @var array<string, mixed> */
    return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}

it('gives the text back as written when only Laravel declares __() and WordPress is absent', function (): void {
    $result = laravelStyleRequest();

    expect($result['translate'])->toBe('Book')
        ->and($result['text'])->toBe('Book')
        ->and($result['resolved']['plain'])->toBe('Book')
        ->and($result['resolved']['domain'])->toBe('Film')
        ->and($result['resolved']['context'])->toBe('Author')
        ->and($result['resolved']['scalar'])->toBe('Untouched');
});

it('builds the plural structures by hand when only Laravel declares __()', function (): void {
    $resolved = laravelStyleRequest()['resolved'];

    expect($resolved['plural'])->toBe([0 => 'One book', 1 => '%d books', 'singular' => 'One book', 'plural' => '%d books', 'context' => null, 'domain' => 'pollora'])
        ->and($resolved['pluralContext'])->toBe([0 => 'One film', 1 => '%d films', 2 => 'cinema', 'singular' => 'One film', 'plural' => '%d films', 'context' => 'cinema', 'domain' => 'own']);
});
