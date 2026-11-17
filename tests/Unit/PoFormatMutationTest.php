<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Catalog;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\CatalogMessage;
use Pollora\I18n\Tooling\PoParseException;
use Pollora\I18n\Tooling\PoReader;
use Pollora\I18n\Tooling\PoWriter;

it('gives a parse exception the code zero and the previous exception it was given', function (): void {
    $previous = new RuntimeException('inner');
    $exception = new PoParseException('boom', 'a.po', 7, $previous);

    expect($exception->getCode())->toBe(0)
        ->and($exception->getPrevious())->toBe($previous)
        ->and($exception->getMessage())->toBe('a.po, line 7: boom');
});

it('keeps an unexpected line whole up to 60 characters and cuts it after', function (int $length, string $expected): void {
    $content = str_repeat('é', $length);

    expect(PoParseException::unexpectedLine('a.po', 3, $content)->getMessage())->toBe('a.po, line 3: unexpected "'.$expected.'"');
})->with([
    'empty' => [0, ''],
    'one' => [1, 'é'],
    'sixty' => [60, str_repeat('é', 60)],
    'sixty one' => [61, str_repeat('é', 60).'...'],
    'long' => [200, str_repeat('é', 60).'...'],
]);

it('cuts an excerpt that is not valid UTF-8 at 60 bytes', function (): void {
    $content = str_repeat("\xFF", 100);

    expect(PoParseException::unexpectedLine('a.po', 1, $content)->getMessage())->toBe('a.po, line 1: unexpected "'.str_repeat("\xFF", 60).'..."');
});

it('keeps an invalid UTF-8 excerpt of 60 bytes or less whole', function (): void {
    $content = str_repeat("\xFF", 60);

    expect(PoParseException::unexpectedLine('a.po', 1, $content)->getMessage())->toBe('a.po, line 1: unexpected "'.$content.'"');
});

it('cuts the msgid of a duplicate message in the same way', function (): void {
    $msgid = str_repeat('a', 70);

    expect(PoParseException::duplicateMessage('a.po', 9, 2, $msgid)->getMessage())
        ->toBe('a.po, line 9: the message "'.str_repeat('a', 60).'..." is already defined at line 2');
});

it('ends an entry at a line made of spaces', function (): void {
    expect(fn () => (new PoReader)->readString("#. first\n   \n#. second\nmsgid \"a\"\nmsgstr \"b\"\n"))
        ->toThrow(PoParseException::class, 'the entry has comments but no msgid');
});

it('starts a new entry at a msgctxt that follows a translation without a blank line', function (): void {
    $catalog = (new PoReader)->readString("msgid \"a\"\nmsgstr \"b\"\nmsgctxt \"c\"\nmsgid \"a\"\nmsgstr \"d\"\n");

    expect($catalog)->toHaveCount(2)
        ->and($catalog->get('a')?->translations)->toBe(['b'])
        ->and($catalog->get("c\x04a")?->translations)->toBe(['d']);
});

it('reads comment lines whose marker is not followed by a space', function (): void {
    $catalog = (new PoReader)->readString("#.note\n#:src/a.php:3 src/b.php:4\n#,fuzzy,php-format\nmsgid \"a\"\nmsgstr \"b\"\n");
    $message = $catalog->get('a');

    expect($message?->entry->comments)->toBe(['note'])
        ->and($message?->entry->references)->toBe(['src/a.php:3', 'src/b.php:4'])
        ->and($message?->flags)->toBe(['fuzzy', 'php-format']);
});

it('trims the flags of a comment', function (): void {
    $catalog = (new PoReader)->readString("#,  fuzzy , php-format   \nmsgid \"a\"\nmsgstr \"b\"\n");

    expect($catalog->get('a')?->flags)->toBe(['fuzzy', 'php-format']);
});

it('appends a continuation line to the last translation of a plural', function (): void {
    $text = "msgid \"a\"\nmsgid_plural \"as\"\nmsgstr[0] \"one\"\nmsgstr[1] \"\"\n\"two \"\n\"more\"\n";
    $catalog = (new PoReader)->readString($text);

    expect($catalog->get('a')?->translations)->toBe(['one', 'two more']);
});

it('writes a flag once however many times the message lists it', function (): void {
    $catalog = new Catalog([], [new CatalogMessage(new CatalogEntry('a'), ['b'], ['php-format', 'fuzzy', 'php-format', 'fuzzy'])]);

    expect((new PoWriter)->write($catalog))->toContain("#, fuzzy, php-format\nmsgid \"a\"");
});

it('sorts references that have no line number by their whole text', function (): void {
    $catalog = new Catalog([], [new CatalogMessage(new CatalogEntry('a', references: ['a1', 'a', 'a:0', 'a:2', 'a:10']), ['b'])]);

    expect((new PoWriter)->write($catalog))->toContain("#: a\n#: a:0\n#: a:2\n#: a:10\n#: a1\n");
});

it('accepts a header whose name is a number', function (): void {
    $catalog = new Catalog(['404' => 'x']);

    expect($catalog->header('404'))->toBe('x');
});
