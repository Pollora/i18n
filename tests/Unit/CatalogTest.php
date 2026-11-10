<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Catalog;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\CatalogMessage;
use Pollora\I18n\Tooling\InvalidCatalogException;
use Pollora\I18n\Tooling\InvalidCatalogMessageException;

it('keeps the headers and the messages in the order given', function (): void {
    $catalog = new Catalog(
        ['Language' => 'fr', 'Content-Type' => 'text/plain; charset=UTF-8'],
        [new CatalogMessage(new CatalogEntry('B')), new CatalogMessage(new CatalogEntry('A'))],
        ['Title'],
    );

    expect(array_keys($catalog->headers))->toBe(['Language', 'Content-Type'])
        ->and($catalog->header('Language'))->toBe('fr')
        ->and($catalog->header('Missing'))->toBeNull()
        ->and($catalog->headerComments)->toBe(['Title'])
        ->and(array_map(static fn (CatalogMessage $m): string => $m->entry->msgid, $catalog->messages()))->toBe(['B', 'A'])
        ->and($catalog)->toHaveCount(2);
});

it('finds a message by its key', function (): void {
    $message = new CatalogMessage(new CatalogEntry('Book', 'post type'));
    $catalog = new Catalog(messages: [$message]);

    expect($catalog->has("post type\x04Book"))->toBeTrue()
        ->and($catalog->get("post type\x04Book"))->toBe($message)
        ->and($catalog->has('Book'))->toBeFalse()
        ->and($catalog->get('Book'))->toBeNull();
});

it('refuses two messages with the same context and message id', function (): void {
    new Catalog(messages: [new CatalogMessage(new CatalogEntry('Book')), new CatalogMessage(new CatalogEntry('Book'))]);
})->throws(InvalidCatalogException::class, 'twice');

it('accepts the same message id with another context', function (): void {
    $catalog = new Catalog(messages: [new CatalogMessage(new CatalogEntry('Book')), new CatalogMessage(new CatalogEntry('Book', 'verb'))]);

    expect($catalog)->toHaveCount(2);
});

it('refuses a header that would break the file', function (string $name, string $value): void {
    new Catalog([$name => $value]);
})->throws(InvalidCatalogException::class)->with([
    'empty name' => ['', 'x'],
    'colon in name' => ['A:B', 'x'],
    'line break in name' => ["A\nB", 'x'],
    'line break in value' => ['A', "x\ny"],
]);

it('reads the number of plural forms from Plural-Forms, two by default', function (): void {
    expect((new Catalog)->pluralCount())->toBe(2)
        ->and((new Catalog(['Plural-Forms' => 'nplurals=1; plural=0;']))->pluralCount())->toBe(1)
        ->and((new Catalog(['Plural-Forms' => 'nplurals=3; plural=(n==1 ? 0 : 1);']))->pluralCount())->toBe(3)
        ->and((new Catalog(['Plural-Forms' => 'nplurals=0;']))->pluralCount())->toBe(2);
});

it('returns a changed copy and leaves the original alone', function (): void {
    $original = new Catalog(['Language' => 'fr'], [new CatalogMessage(new CatalogEntry('A')), new CatalogMessage(new CatalogEntry('B'))], ['Old']);

    $renamed = $original->withHeader('Language', 'de')->withHeader('X-Domain', 'app')->withHeaderComments(['New']);
    $replaced = $original->withMessage((new CatalogMessage(new CatalogEntry('A')))->withTranslations(['Un']));
    $added = $original->withMessage(new CatalogMessage(new CatalogEntry('C')));
    $removed = $original->withoutMessage('A');
    $swapped = $original->withMessages([new CatalogMessage(new CatalogEntry('Z'))]);
    $reheadered = $original->withHeaders(['A' => 'b']);

    expect($original->header('Language'))->toBe('fr')
        ->and($original->headerComments)->toBe(['Old'])
        ->and($renamed->headers)->toBe(['Language' => 'de', 'X-Domain' => 'app'])
        ->and($renamed->headerComments)->toBe(['New'])
        ->and($renamed)->toHaveCount(2)
        ->and($replaced->get('A')?->translations)->toBe(['Un'])
        ->and(array_keys(array_column(array_map(static fn (CatalogMessage $m): array => [$m->entry->msgid], $replaced->messages()), 0)))->toBe([0, 1])
        ->and($replaced->messages()[0]->entry->msgid)->toBe('A')
        ->and($added)->toHaveCount(3)
        ->and($removed->has('A'))->toBeFalse()
        ->and($removed)->toHaveCount(1)
        ->and($swapped)->toHaveCount(1)
        ->and($reheadered->headers)->toBe(['A' => 'b'])
        ->and($reheadered)->toHaveCount(2)
        ->and($original)->toHaveCount(2);
});

it('describes a message and returns changed copies', function (): void {
    $message = new CatalogMessage(new CatalogEntry('Book', plural: 'Books'));

    expect($message->isPlural())->toBeTrue()
        ->and($message->isTranslated())->toBeFalse()
        ->and($message->isFuzzy())->toBeFalse()
        ->and($message->obsolete)->toBeFalse()
        ->and($message->key())->toBe('Book')
        ->and($message->withTranslations(['Livre', ''])->isTranslated())->toBeFalse()
        ->and($message->withTranslations(['Livre', 'Livres'])->isTranslated())->toBeTrue()
        ->and($message->withFlags(['fuzzy'])->isFuzzy())->toBeTrue()
        ->and($message->withObsolete()->obsolete)->toBeTrue()
        ->and($message->withObsolete()->withObsolete(false)->obsolete)->toBeFalse()
        ->and($message->withTranslatorComments(['note'])->translatorComments)->toBe(['note'])
        ->and($message->withEntry(new CatalogEntry('Other'))->entry->msgid)->toBe('Other')
        ->and($message->translations)->toBe([]);
});

it('refuses several translations for a singular message', function (): void {
    new CatalogMessage(new CatalogEntry('Book'), ['a', 'b']);
})->throws(InvalidCatalogMessageException::class, 'one');

it('refuses a flag that would break the file', function (string $flag): void {
    new CatalogMessage(new CatalogEntry('Book'), flags: [$flag]);
})->throws(InvalidCatalogMessageException::class)->with(['empty' => [''], 'comma' => ['a,b'], 'line break' => ["a\nb"]]);
