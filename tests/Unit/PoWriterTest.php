<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Catalog;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\CatalogMessage;
use Pollora\I18n\Tooling\PoWriter;

const FR_HEADERS = [
    'Project-Id-Version' => 'app 1.0',
    'Language' => 'fr',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Plural-Forms' => 'nplurals=2; plural=(n > 1);',
];

it('writes the header, then the messages, with a blank line between entries', function (): void {
    $catalog = new Catalog(
        ['Language' => 'fr', 'Plural-Forms' => 'nplurals=2; plural=(n > 1);'],
        [
            new CatalogMessage(new CatalogEntry('Book', null, 'Books', ['translators: noun'], ['src/b.php:10', 'src/a.php:9', 'src/a.php:10', 'src/a.php:9']), ['Livre', 'Livres'], ['php-format', 'fuzzy'], translatorComments: ['checked']),
            new CatalogMessage(new CatalogEntry('Add', 'verb'), ['Ajouter']),
        ],
        ['Translations for app.', 'Same licence.'],
    );

    expect((new PoWriter)->write($catalog))->toBe(<<<'PO'
# Translations for app.
# Same licence.
msgid ""
msgstr ""
"Language: fr\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"

msgctxt "verb"
msgid "Add"
msgstr "Ajouter"

# checked
#. translators: noun
#: src/a.php:9
#: src/a.php:10
#: src/b.php:10
#, fuzzy, php-format
msgid "Book"
msgid_plural "Books"
msgstr[0] "Livre"
msgstr[1] "Livres"

PO);
});

it('writes nothing for an empty catalogue and no header block without headers', function (): void {
    expect((new PoWriter)->write(new Catalog))->toBe('')
        ->and((new PoWriter)->write(new Catalog(messages: [new CatalogMessage(new CatalogEntry('A'))])))->toBe("msgid \"A\"\nmsgstr \"\"\n")
        ->and((new PoWriter)->write(new Catalog(headerComments: ['Only a note'])))->toBe("# Only a note\nmsgid \"\"\nmsgstr \"\"\n");
});

it('escapes backslash, quote, tab, return and control characters', function (): void {
    $catalog = new Catalog(messages: [new CatalogMessage(new CatalogEntry("a\\b \"q\" \t\r\x01\x07\x08\x0b\x0c\x1f é 日本"), ['plain'])]);

    expect((new PoWriter)->write($catalog))->toBe('msgid "a\\\\b \\"q\\" \\t\\r\\001\\a\\b\\v\\f\\037 é 日本"'."\nmsgstr \"plain\"\n");
});

it('breaks a string after each line break it holds, as msgcat does', function (): void {
    $catalog = new Catalog(messages: [
        new CatalogMessage(new CatalogEntry("one\ntwo\n\nthree\n"), ["x\n"]),
        new CatalogMessage(new CatalogEntry("trailing\n"), ["a\nb"]),
    ]);

    expect((new PoWriter)->write($catalog))->toBe(<<<'PO'
msgid ""
"one\n"
"two\n"
"\n"
"three\n"
msgstr "x\n"

msgid "trailing\n"
msgstr ""
"a\n"
"b"

PO);
});

it('writes one msgstr per plural form of the language, and as many as there are translations if more', function (): void {
    $plural = new CatalogEntry('File', plural: 'Files');
    $write = static fn (array $headers, array $translations): string => (new PoWriter)->write(new Catalog($headers, [new CatalogMessage($plural, $translations)]));

    expect($write([], []))->toContain('msgstr[0] ""')->toContain('msgstr[1] ""')->not->toContain('msgstr[2]')
        ->and($write(['Plural-Forms' => 'nplurals=1; plural=0;'], []))->toContain('msgstr[0] ""')->not->toContain('msgstr[1]')
        ->and($write(['Plural-Forms' => 'nplurals=3; plural=0;'], ['a']))->toContain('msgstr[0] "a"')->toContain('msgstr[2] ""')
        ->and($write(['Plural-Forms' => 'nplurals=1; plural=0;'], ['a', 'b']))->toContain('msgstr[1] "b"');
});

it('writes obsolete messages last, with #~ and without locations', function (): void {
    $catalog = new Catalog(messages: [
        (new CatalogMessage(new CatalogEntry("old\nline", 'ctx', 'olds', ['dropped'], ['a.php:1']), ['ancien', 'anciens'], ['fuzzy'], true, ['kept'])),
        new CatalogMessage(new CatalogEntry('Zed'), ['Z']),
    ]);

    expect((new PoWriter)->write($catalog))->toBe(<<<'PO'
msgid "Zed"
msgstr "Z"

# kept
#, fuzzy
#~ msgctxt "ctx"
#~ msgid ""
#~ "old\n"
#~ "line"
#~ msgid_plural "olds"
#~ msgstr[0] "ancien"
#~ msgstr[1] "anciens"

PO);
});

it('gives each line of a multi-line comment its own marker', function (): void {
    $catalog = new Catalog(messages: [new CatalogMessage(new CatalogEntry('A', comments: ["first\nsecond", '', ' indented']))]);

    expect((new PoWriter)->write($catalog))->toBe("#. first\n#. second\n#.\n#.  indented\nmsgid \"A\"\nmsgstr \"\"\n");
});

it('writes the same text whatever the order of the messages, the references and the flags', function (): void {
    $messages = [
        new CatalogMessage(new CatalogEntry('10'), ['x']),
        new CatalogMessage(new CatalogEntry('9', references: ['b.php:2', 'a.php:20', 'a.php:3', 'a.php', 'a.php:03'])),
        new CatalogMessage(new CatalogEntry('Apple', 'z')),
        new CatalogMessage(new CatalogEntry('Apple', 'a')),
        new CatalogMessage(new CatalogEntry('Apple')),
        new CatalogMessage(new CatalogEntry('Apple', '')),
        new CatalogMessage(new CatalogEntry('gone'), [], ['b', 'a', 'a'], true),
        new CatalogMessage(new CatalogEntry('Äpfel')),
    ];
    $writer = new PoWriter;
    $expected = $writer->write(new Catalog(FR_HEADERS, $messages));

    mt_srand(7);

    for ($round = 0; $round < 20; $round++) {
        $shuffled = $messages;
        shuffle($shuffled);
        $shuffled[0] = $shuffled[0]->withFlags(array_reverse($shuffled[0]->flags));

        expect($writer->write(new Catalog(FR_HEADERS, $shuffled)))->toBe($expected);
    }

    expect(strpos($expected, 'msgid "10"'))->toBeLessThan(strpos($expected, 'msgid "9"'))
        ->and(strpos($expected, 'msgid "9"'))->toBeLessThan(strpos($expected, 'msgid "Apple"'))
        ->and(strpos($expected, 'msgid "Äpfel"'))->toBeLessThan(strpos($expected, 'msgid "gone"'))
        ->and($expected)->toContain("#: a.php\n#: a.php:03\n#: a.php:3\n#: a.php:20\n#: b.php:2\n");
});

it('sorts a message without context before the same message with one', function (): void {
    $out = (new PoWriter)->write(new Catalog(messages: [
        new CatalogMessage(new CatalogEntry('A', 'b')),
        new CatalogMessage(new CatalogEntry('A', '')),
        new CatalogMessage(new CatalogEntry('A')),
    ]));

    expect(preg_match_all('/msgctxt "(.*)"/', $out, $contexts))->toBe(2)
        ->and($contexts[1])->toBe(['', 'b'])
        ->and(strpos($out, "msgid \"A\"\nmsgstr \"\"\n\nmsgctxt"))->toBe(0);
});
