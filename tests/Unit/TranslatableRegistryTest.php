<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;

beforeEach(function (): void {
    TranslatableRegistry::flush();
});

it('knows nothing about a class that was never declared', function (): void {
    expect(TranslatableRegistry::fieldsFor(stdClass::class))->toBe([]);
});

it('returns the fields declared for a class', function (): void {
    TranslatableRegistry::register(stdClass::class, ['singular', 'plural']);

    expect(TranslatableRegistry::fieldsFor(stdClass::class))->toBe(['singular', 'plural']);
});

it('adds to the fields of a class declared twice, without duplicates', function (): void {
    TranslatableRegistry::register(stdClass::class, ['singular', 'plural']);
    TranslatableRegistry::register(stdClass::class, ['plural', 'description']);

    expect(TranslatableRegistry::fieldsFor(stdClass::class))->toBe(['singular', 'plural', 'description']);
});

it('keeps the classes apart', function (): void {
    TranslatableRegistry::register(stdClass::class, ['a']);
    TranslatableRegistry::register(ArrayObject::class, ['b']);

    expect(TranslatableRegistry::fieldsFor(stdClass::class))->toBe(['a'])
        ->and(TranslatableRegistry::fieldsFor(ArrayObject::class))->toBe(['b']);
});

it('forgets everything on flush', function (): void {
    TranslatableRegistry::register(stdClass::class, ['a']);
    TranslatableRegistry::flush();

    expect(TranslatableRegistry::fieldsFor(stdClass::class))->toBe([]);
});
