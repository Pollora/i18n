<?php

declare(strict_types=1);

it('autoloads no Tooling class when a request registers and translates', function (): void {
    foreach (['none', 'wordpress'] as $mode) {
        expect(simulateRequest($mode)['tooling'])->toBe([]);
    }
});

it('requires nothing but PHP, and suggests the heavy libraries the tooling may use', function (): void {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($composer['require']))->toBe(['php'])
        ->and($composer['suggest'])->toHaveKeys(['nikic/php-parser', 'symfony/console']);
});
