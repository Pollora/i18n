<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Catalog;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\CatalogMessage;
use Pollora\I18n\Tooling\PoFileException;
use Pollora\I18n\Tooling\PoParseException;
use Pollora\I18n\Tooling\PoReader;
use Pollora\I18n\Tooling\PoWriter;

/**
 * A catalogue that uses everything the format has.
 */
function fullCatalog(): Catalog
{
    return new Catalog(
        [
            'Project-Id-Version' => 'app 1.0',
            'Language' => 'ru',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Plural-Forms' => 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : 1);',
            'X-Empty' => '',
        ],
        [
            new CatalogMessage(new CatalogEntry('Book', null, 'Books', ['translators: a noun'], ['src/a.php:3', 'src/b.php:40']), ['Книга', 'Книги', 'Книг'], ['fuzzy', 'php-format'], translatorComments: ['reviewed', '']),
            new CatalogMessage(new CatalogEntry('Add', 'verb'), ['Ajouter']),
            new CatalogMessage(new CatalogEntry('Add', 'noun'), ['Ajout']),
            new CatalogMessage(new CatalogEntry('Escapes'), ["a\\b \"q\" \t\r\x01\x07\x08\x0b\x0c\x1f é 日本 \\n"]),
            new CatalogMessage(new CatalogEntry("Multi\nline\n\nstring\n"), ["Multi\nligne\n"]),
            new CatalogMessage(new CatalogEntry('Multi context', "first\nsecond", "Multi\nplurals"), ['Un', 'Deux', 'Trois']),
            new CatalogMessage(new CatalogEntry('Untranslated')),
            new CatalogMessage(new CatalogEntry('Empty plural', plural: 'Empty plurals')),
            new CatalogMessage(new CatalogEntry('Gone', 'ctx', 'Gones'), ['Parti', 'Partis', 'Partis'], ['fuzzy'], true, ['was here']),
            new CatalogMessage(new CatalogEntry("Gone\nmulti"), ['Parti'], obsolete: true),
        ],
        ['Translations for app.', '', 'Same licence.'],
    );
}

it('reads back, byte for byte, what the writer wrote', function (): void {
    $writer = new PoWriter;
    $text = $writer->write(fullCatalog());
    $read = (new PoReader)->readString($text);

    expect($writer->write($read))->toBe($text)
        ->and($read->headers)->toBe(fullCatalog()->headers)
        ->and($read->headerComments)->toBe(['Translations for app.', '', 'Same licence.'])
        ->and($read)->toHaveCount(10);

    $book = $read->get('Book');
    $add = $read->get("verb\x04Add");

    expect($book?->translations)->toBe(['Книга', 'Книги', 'Книг'])
        ->and($book?->entry->comments)->toBe(['translators: a noun'])
        ->and($book?->entry->references)->toBe(['src/a.php:3', 'src/b.php:40'])
        ->and($book?->flags)->toBe(['fuzzy', 'php-format'])
        ->and($book?->translatorComments)->toBe(['reviewed', ''])
        ->and($add?->translations)->toBe(['Ajouter'])
        ->and($read->get('Escapes')?->translations)->toBe(["a\\b \"q\" \t\r\x01\x07\x08\x0b\x0c\x1f é 日本 \\n"])
        ->and($read->get("Multi\nline\n\nstring\n")?->translations)->toBe(["Multi\nligne\n"])
        ->and($read->get("first\nsecond\x04Multi context")?->entry->plural)->toBe("Multi\nplurals")
        ->and($read->get("ctx\x04Gone")?->obsolete)->toBeTrue()
        ->and($read->get("ctx\x04Gone")?->translatorComments)->toBe(['was here'])
        ->and($read->get("Gone\nmulti")?->obsolete)->toBeTrue()
        ->and($read->get('Untranslated')?->obsolete)->toBeFalse();
});

it('reads a file from disk, and an empty one', function (): void {
    $path = sys_get_temp_dir().'/pollora-i18n-read-'.bin2hex(random_bytes(4)).'.po';
    file_put_contents($path, (new PoWriter)->write(fullCatalog()));

    try {
        expect((new PoReader)->read($path))->toHaveCount(10);

        file_put_contents($path, '');

        expect((new PoReader)->read($path))->toHaveCount(0)->and((new PoReader)->read($path)->headers)->toBe([]);
    } finally {
        unlink($path);
    }
});

it('fails on a file it cannot open', function (): void {
    (new PoReader)->read(sys_get_temp_dir().'/pollora-i18n-missing/none.po');
})->throws(PoFileException::class, 'cannot be opened');

it('tolerates CRLF line ends and a byte order mark', function (): void {
    $text = (new PoWriter)->write(fullCatalog());
    $crlf = "\xEF\xBB\xBF".str_replace("\n", "\r\n", $text);

    expect((new PoWriter)->write((new PoReader)->readString($crlf)))->toBe($text);
});

it('reads what msgcat and Poedit write: packed references, previous strings, no blank before a comment', function (): void {
    $text = <<<'PO'
# SOME DESCRIPTIVE TITLE.
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: x\n"
"Language: fr\n"
"Content-Type: text/plain; charset=UTF-8\n"

#: a.php:1   b.php:2
#, fuzzy,   php-format
#| msgid "Old"
msgid "New %s"
msgstr "Nouveau %s"
#. note
msgid "Glued"
msgstr "Collé"
msgid "Next"
msgstr  "Suivant"

#~| msgid "x"
#~ msgid "Obsolete"
#~ msgstr "Obsolète"
#~
PO;

    $catalog = (new PoReader)->readString($text);

    expect($catalog->headerComments)->toBe(['SOME DESCRIPTIVE TITLE.'])
        ->and($catalog->header('Project-Id-Version'))->toBe('x')
        ->and($catalog->get('New %s')?->entry->references)->toBe(['a.php:1', 'b.php:2'])
        ->and($catalog->get('New %s')?->flags)->toBe(['fuzzy', 'php-format'])
        ->and($catalog->get('Glued')?->entry->comments)->toBe(['note'])
        ->and($catalog->get('Next')?->translations)->toBe(['Suivant'])
        ->and($catalog->get('Obsolete')?->obsolete)->toBeTrue()
        ->and($catalog)->toHaveCount(4);
});

it('reads the escapes msgfmt accepts', function (): void {
    $catalog = (new PoReader)->readString('msgid "x"'."\n".'msgstr "\\a\\b\\f\\n\\r\\t\\v\\\\\\"\\101\\0\\x41\\x4a\\1012"'."\n");

    expect($catalog->get('x')?->translations)->toBe(["\x07\x08\x0c\n\r\t\x0b\\\"A\0AJA2"]);
});

it('reads a header whose value has no space after the colon, and keeps a colon inside a value', function (): void {
    $catalog = (new PoReader)->readString("msgid \"\"\nmsgstr \"\"\n\"A:b\\n\"\n\"\\n\"\n\"POT-Creation-Date: 2026-11-10 10:00+0000\\n\"\n");

    expect($catalog->headers)->toBe(['A' => 'b', 'POT-Creation-Date' => '2026-11-10 10:00+0000']);
});

it('reports the line of a malformed file', function (string $text, int $line, string $fragment): void {
    try {
        (new PoReader)->readString($text);
    } catch (PoParseException $exception) {
        expect($exception->lineNumber)->toBe($line)
            ->and($exception->source)->toBe('(string)')
            ->and($exception->getMessage())->toContain("line $line")->toContain($fragment);

        return;
    }

    throw new RuntimeException('No exception was raised.');
})->with([
    'garbage' => ["msgid \"a\"\nmsgstr \"b\"\n\nhello world\n", 4, 'unexpected "hello world"'],
    'unclosed string' => ["msgid \"a\nmsgstr \"b\"\n", 1, 'unexpected'],
    'unclosed continuation' => ["msgid \"a\"\nmsgstr \"b\"\n\"c\n", 3, 'not closed'],
    'lone quote' => ["msgid \"a\"\nmsgstr \"b\"\n\"\n", 3, 'not closed'],
    'inner quote' => ["msgid \"a\"\nmsgstr \"b\"c\"\n", 2, 'unescaped quote'],
    'unknown escape' => ["msgid \"a\"\nmsgstr \"\\q\"\n", 2, 'escape sequence "\\q"'],
    'continuation first' => ["\"a\"\n", 1, 'unexpected'],
    'continuation after comment' => ["#. c\n\"a\"\n", 2, 'unexpected'],
    'unknown comment' => ["#! x\nmsgid \"a\"\nmsgstr \"\"\n", 1, 'unexpected "#! x"'],
    'comment without message' => ["\n\n#. x\n\n", 3, 'no msgid'],
    'msgid without msgstr' => ["msgid \"a\"\n\nmsgid \"b\"\nmsgstr \"\"\n", 1, 'no msgstr'],
    'msgid then comment' => ["msgid \"a\"\n#. x\nmsgstr \"\"\n", 1, 'no msgstr'],
    'msgstr without msgid' => ["msgstr \"a\"\n", 1, 'unexpected'],
    'two msgid' => ["msgid \"a\"\nmsgid \"b\"\nmsgstr \"\"\n", 2, 'unexpected'],
    'two msgctxt' => ["msgctxt \"a\"\nmsgctxt \"b\"\nmsgid \"c\"\nmsgstr \"\"\n", 2, 'unexpected'],
    'msgctxt after msgid' => ["msgid \"a\"\nmsgctxt \"b\"\nmsgstr \"\"\n", 2, 'unexpected'],
    'plural without msgid' => ["msgid_plural \"a\"\n", 1, 'unexpected'],
    'two plurals' => ["msgid \"a\"\nmsgid_plural \"b\"\nmsgid_plural \"c\"\n", 3, 'unexpected'],
    'plural after msgstr' => ["msgid \"a\"\nmsgstr \"\"\nmsgid_plural \"b\"\n", 3, 'unexpected'],
    'msgstr[] on singular' => ["msgid \"a\"\nmsgstr[0] \"b\"\n", 2, 'unexpected'],
    'msgstr on plural' => ["msgid \"a\"\nmsgid_plural \"b\"\nmsgstr \"c\"\n", 3, 'unexpected'],
    'msgstr out of order' => ["msgid \"a\"\nmsgid_plural \"b\"\nmsgstr[1] \"c\"\n", 3, 'unexpected'],
    'two msgstr' => ["msgid \"a\"\nmsgstr \"b\"\nmsgstr \"c\"\n", 3, 'unexpected'],
    'indexed msgid' => ["msgid[0] \"a\"\n", 1, 'unexpected'],
    'obsolete mixed with active' => ["msgid \"a\"\n#~ msgstr \"b\"\n", 2, 'unexpected'],
    'obsolete continuation of active' => ["msgid \"a\"\n#~ \"b\"\n", 2, 'unexpected'],
    'header without colon' => ["\n\nmsgid \"\"\nmsgstr \"\"\n\"no colon\\n\"\n", 3, 'has no colon'],
    'two headers' => ["msgid \"\"\nmsgstr \"\"\n\nmsgid \"\"\nmsgstr \"\"\n", 4, 'second header'],
    'duplicate message' => ["msgid \"a\"\nmsgstr \"\"\n\nmsgid \"b\"\nmsgstr \"\"\n\nmsgid \"a\"\nmsgstr \"\"\n", 7, 'already defined at line 1'],
    'empty plural' => ["msgid \"a\"\nmsgid_plural \"\"\nmsgstr[0] \"\"\n", 1, 'plural form'],
    'empty msgid with context' => ["msgctxt \"c\"\nmsgid \"\"\nmsgstr \"\"\n", 1, 'non-empty'],
]);

it('keeps a previous exception behind an invalid entry', function (): void {
    try {
        (new PoReader)->readString("msgctxt \"c\"\nmsgid \"\"\nmsgstr \"\"\n");
    } catch (PoParseException $exception) {
        expect($exception->getPrevious())->not->toBeNull();

        return;
    }

    throw new RuntimeException('No exception was raised.');
});

it('names the file in the message', function (): void {
    $path = sys_get_temp_dir().'/pollora-i18n-bad-'.bin2hex(random_bytes(4)).'.po';
    file_put_contents($path, "msgid \"a\"\nmsgstr \"b\"\n\nnope\n");

    try {
        (new PoReader)->read($path);
    } catch (PoParseException $exception) {
        expect($exception->getMessage())->toStartWith($path.', line 4');

        return;
    } finally {
        unlink($path);
    }

    throw new RuntimeException('No exception was raised.');
});
