<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\InvalidCatalogEntryException;

it('holds a message id, a context, a plural, comments and references', function (): void {
    $entry = new CatalogEntry('Book', 'post type', 'Books', ['translators: noun'], ['src/Book.php:12']);

    expect($entry->msgid)->toBe('Book')
        ->and($entry->msgctxt)->toBe('post type')
        ->and($entry->plural)->toBe('Books')
        ->and($entry->comments)->toBe(['translators: noun'])
        ->and($entry->references)->toBe(['src/Book.php:12'])
        ->and($entry->isPlural())->toBeTrue();
});

it('is a singular string without context by default', function (): void {
    $entry = new CatalogEntry('Book');

    expect($entry->msgctxt)->toBeNull()
        ->and($entry->isPlural())->toBeFalse()
        ->and($entry->comments)->toBe([])
        ->and($entry->references)->toBe([]);
});

it('keys an entry by its message id, joined to its context the way gettext does', function (): void {
    expect((new CatalogEntry('Book'))->key())->toBe('Book')
        ->and((new CatalogEntry('Book', 'post type'))->key())->toBe("post type\x04Book")
        ->and((new CatalogEntry('Book', 'post type'))->key())->not->toBe((new CatalogEntry('Book', 'verb'))->key());
});

it('refuses the empty message id, which is the header of a file', function (): void {
    new CatalogEntry('');
})->throws(InvalidCatalogEntryException::class, 'message id');

it('refuses an empty plural form', function (): void {
    new CatalogEntry('Book', plural: '');
})->throws(InvalidCatalogEntryException::class, 'plural');
