<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Catalog;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\CatalogMerger;
use Pollora\I18n\Tooling\CatalogMessage;
use Pollora\I18n\Tooling\PoReader;
use Pollora\I18n\Tooling\PoWriter;

/**
 * @param  list<string>  $translations
 * @param  list<string>  $flags
 * @param  list<string>  $references
 */
function mergeMessage(string $msgid, array $translations = [], array $flags = [], bool $obsolete = false, ?string $plural = null, array $references = [], ?string $context = null): CatalogMessage
{
    return new CatalogMessage(new CatalogEntry($msgid, $context, $plural, [], $references), $translations, $flags, $obsolete);
}

/**
 * @param  list<CatalogMessage>  $messages
 * @param  array<string, string>  $headers
 */
function mergeCatalog(array $messages, array $headers = ['Language' => 'fr', 'Plural-Forms' => 'nplurals=2; plural=(n > 1);']): Catalog
{
    return new Catalog($headers, $messages);
}

function mergeText(Catalog $catalog): string
{
    return (new PoWriter)->write($catalog);
}

it('adds new messages untranslated and updates references and comments from the template', function (): void {
    $existing = mergeCatalog([mergeMessage('Book', ['Livre'], references: ['old.php:1'])]);
    $fresh = mergeCatalog([
        new CatalogMessage(new CatalogEntry('Book', null, null, ['translators: noun'], ['new.php:2'])),
        new CatalogMessage(new CatalogEntry('Page', 'ctx', null, [], ['p.php:3']), ['ignored'], ['php-format', 'fuzzy'], translatorComments: ['seen']),
    ]);

    $merged = (new CatalogMerger)->merge($existing, $fresh);

    expect($merged->get('Book')->translations)->toBe(['Livre'])
        ->and($merged->get('Book')->entry->references)->toBe(['new.php:2'])
        ->and($merged->get('Book')->entry->comments)->toBe(['translators: noun'])
        ->and($merged->get("ctx\x04Page")->translations)->toBe([])
        ->and($merged->get("ctx\x04Page")->flags)->toBe(['php-format'])
        ->and($merged->get("ctx\x04Page")->translatorComments)->toBe(['seen'])
        ->and($merged->get("ctx\x04Page")->obsolete)->toBeFalse();
});

it('keeps existing translations, translator comments and fuzzy as they are', function (): void {
    $existing = mergeCatalog([new CatalogMessage(new CatalogEntry('Book'), ['Livre'], ['fuzzy', 'c-format'], false, ['checked'])]);
    $fresh = mergeCatalog([mergeMessage('Book', [], ['php-format'])]);

    $book = (new CatalogMerger)->merge($existing, $fresh)->get('Book');

    expect($book->translations)->toBe(['Livre'])
        ->and($book->flags)->toBe(['fuzzy', 'c-format', 'php-format'])
        ->and($book->translatorComments)->toBe(['checked']);
});

it('never sets fuzzy because the template says so, nor clears it because the template does not', function (): void {
    $existing = mergeCatalog([mergeMessage('A', ['a']), mergeMessage('B', ['b'], ['fuzzy'])]);
    $fresh = mergeCatalog([mergeMessage('A', [], ['fuzzy']), mergeMessage('B')]);

    $merged = (new CatalogMerger)->merge($existing, $fresh);

    expect($merged->get('A')->isFuzzy())->toBeFalse()
        ->and($merged->get('B')->isFuzzy())->toBeTrue();
});

it('adjusts the plural forms to the language without losing a translation', function (): void {
    $russian = ['Language' => 'ru', 'Plural-Forms' => 'nplurals=3; plural=0;'];
    $existing = mergeCatalog([
        mergeMessage('Book', ['Livre']),
        mergeMessage('Page', ['a', 'b'], plural: 'Pages'),
        mergeMessage('Same', ['a', 'b', 'c'], plural: 'Sames'),
        mergeMessage('Gone', ['a', 'b', 'c'], plural: 'Gones'),
        mergeMessage('Empty', plural: 'Empties'),
    ], $russian);
    $fresh = mergeCatalog([
        mergeMessage('Book', plural: 'Books'),
        mergeMessage('Page', plural: 'Pages'),
        mergeMessage('Same', plural: 'Sames'),
        mergeMessage('Gone'),
        mergeMessage('Empty', plural: 'Empties'),
    ]);

    $merged = (new CatalogMerger)->merge($existing, $fresh);

    expect($merged->get('Book')->translations)->toBe(['Livre', '', ''])
        ->and($merged->get('Book')->entry->plural)->toBe('Books')
        ->and($merged->get('Page')->translations)->toBe(['a', 'b', ''])
        ->and($merged->get('Same')->translations)->toBe(['a', 'b', 'c'])
        ->and($merged->get('Gone')->translations)->toBe(['a'])
        ->and($merged->get('Gone')->isPlural())->toBeFalse()
        ->and($merged->get('Empty')->translations)->toBe([]);
});

it('truncates the forms a language no longer has, as its header says', function (): void {
    $existing = mergeCatalog([mergeMessage('Book', ['a', 'b', 'c'], plural: 'Books')]);
    $fresh = mergeCatalog([mergeMessage('Book', plural: 'Books')]);

    expect((new CatalogMerger)->merge($existing, $fresh)->get('Book')->translations)->toBe(['a', 'b']);
});

it('makes the messages the template no longer has obsolete instead of dropping them', function (): void {
    $existing = mergeCatalog([mergeMessage('Kept', ['k']), mergeMessage('Lost', ['l']), mergeMessage('Old', ['o'], obsolete: true)]);
    $fresh = mergeCatalog([mergeMessage('Kept')]);

    $merged = (new CatalogMerger)->merge($existing, $fresh);

    expect($merged->get('Lost')->obsolete)->toBeTrue()
        ->and($merged->get('Lost')->translations)->toBe(['l'])
        ->and($merged->get('Old')->obsolete)->toBeTrue()
        ->and($merged->get('Kept')->obsolete)->toBeFalse();
});

it('brings an obsolete message back with its translation when the template has it again', function (): void {
    $existing = mergeCatalog([mergeMessage('Back', ['Retour'], obsolete: true)]);
    $fresh = mergeCatalog([mergeMessage('Back', references: ['x.php:1'])]);

    $back = (new CatalogMerger)->merge($existing, $fresh)->get('Back');

    expect($back->obsolete)->toBeFalse()
        ->and($back->translations)->toBe(['Retour'])
        ->and($back->entry->references)->toBe(['x.php:1']);
});

it('ignores the obsolete messages of the template', function (): void {
    $existing = mergeCatalog([mergeMessage('Live', ['v'])]);
    $fresh = mergeCatalog([mergeMessage('Live', obsolete: true), mergeMessage('Dead', obsolete: true)]);

    $merged = (new CatalogMerger)->merge($existing, $fresh);

    expect($merged->get('Live')->obsolete)->toBeTrue()
        ->and($merged->has('Dead'))->toBeFalse();
});

it('keeps the headers and takes the version, and the creation date when messages changed', function (): void {
    $headers = ['Project-Id-Version' => 'app 1.0', 'Language' => 'fr', 'Plural-Forms' => 'nplurals=2; plural=(n > 1);', 'POT-Creation-Date' => '2026-01-01 10:00+0000'];
    $freshHeaders = ['Project-Id-Version' => 'app 1.1', 'POT-Creation-Date' => '2026-02-02 10:00+0000', 'Language' => 'en'];
    $existing = new Catalog($headers, [mergeMessage('A', ['a'])], ['Title']);

    $unchanged = (new CatalogMerger)->merge($existing, new Catalog($freshHeaders, [mergeMessage('A')]));
    $changed = (new CatalogMerger)->merge($existing, new Catalog($freshHeaders, [mergeMessage('A'), mergeMessage('B')]));

    expect($unchanged->headers)->toBe([...$headers, 'Project-Id-Version' => 'app 1.1'])
        ->and($changed->headers)->toBe([...$headers, 'Project-Id-Version' => 'app 1.1', 'POT-Creation-Date' => '2026-02-02 10:00+0000'])
        ->and($changed->headerComments)->toBe(['Title']);
});

it('leaves the headers alone when the template states none', function (): void {
    $existing = mergeCatalog([mergeMessage('A', ['a'])]);

    expect((new CatalogMerger)->merge($existing, mergeCatalog([mergeMessage('B')], []))->headers)->toBe($existing->headers);
});

it('is idempotent: merging the result with the same template again changes nothing', function (): void {
    $existing = mergeCatalog([mergeMessage('Book', ['Livre'], ['fuzzy']), mergeMessage('Lost', ['x']), mergeMessage('Page', ['a'], obsolete: true)]);
    $fresh = new Catalog(['Project-Id-Version' => 'app 2', 'POT-Creation-Date' => 'now'], [
        mergeMessage('Book', plural: 'Books', references: ['a.php:1']),
        mergeMessage('Page'),
        mergeMessage('New', flags: ['php-format']),
    ]);

    $merger = new CatalogMerger;
    $once = $merger->merge($existing, $fresh);
    $twice = $merger->merge($once, $fresh);

    expect(mergeText($twice))->toBe(mergeText($once))
        ->and($twice)->toEqual($once);
});

it('writes the same text whatever the order of the template messages', function (): void {
    $existing = mergeCatalog([mergeMessage('A', ['a'])]);
    $one = [mergeMessage('B'), mergeMessage('C'), mergeMessage('D')];

    $merger = new CatalogMerger;

    expect(mergeText($merger->merge($existing, mergeCatalog($one))))
        ->toBe(mergeText($merger->merge($existing, mergeCatalog(array_reverse($one)))));
});

it('survives a round trip through the writer and the reader', function (): void {
    $reader = new PoReader;
    $existing = $reader->readString(<<<'PO'
msgid ""
msgstr ""
"Language: fr\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"

#, fuzzy
msgid "Book"
msgstr "Livre"

msgid "Old"
msgstr "Vieux"
PO);
    $fresh = $reader->readString(<<<'PO'
msgid ""
msgstr ""
"Project-Id-Version: app 3\n"

#: src/a.php:3
msgid "Book"
msgid_plural "Books"
msgstr[0] ""
msgstr[1] ""

msgid "New"
msgstr ""
PO);

    $merged = (new CatalogMerger)->merge($existing, $fresh);
    $reloaded = $reader->readString(mergeText($merged));

    expect($reloaded)->toEqual($reader->readString(mergeText($reloaded)))
        ->and($reloaded->get('Book')->translations)->toBe(['Livre', ''])
        ->and($reloaded->get('Book')->isFuzzy())->toBeTrue()
        ->and($reloaded->get('Old')->obsolete)->toBeTrue()
        ->and($reloaded->has('New'))->toBeTrue()
        ->and($reloaded->header('Project-Id-Version'))->toBe('app 3');
});

it('unions templates by key, adding references and comments without duplicates', function (): void {
    $base = new Catalog(['Project-Id-Version' => 'ours'], [
        new CatalogMessage(new CatalogEntry('Book', null, null, ['c1'], ['a.php:1']), ['Livre'], ['php-format'], false, ['t1']),
        mergeMessage('Only base', obsolete: true),
        mergeMessage('Ctx', context: 'x'),
    ], ['Header']);
    $other = new Catalog(['Project-Id-Version' => 'theirs'], [
        new CatalogMessage(new CatalogEntry('Book', null, 'Books', ['c1', 'c2'], ['a.php:1', 'b.js:2']), ['ignored'], ['fuzzy'], false, ['t1', 't2']),
        mergeMessage('Only other'),
        mergeMessage('Ctx', context: 'y'),
        mergeMessage('Only base', obsolete: true),
    ]);

    $merged = (new CatalogMerger)->mergeAll($base, $other);

    expect($merged->count())->toBe(5)
        ->and($merged->headers)->toBe(['Project-Id-Version' => 'ours'])
        ->and($merged->headerComments)->toBe(['Header'])
        ->and($merged->get('Book')->entry->references)->toBe(['a.php:1', 'b.js:2'])
        ->and($merged->get('Book')->entry->comments)->toBe(['c1', 'c2'])
        ->and($merged->get('Book')->entry->plural)->toBe('Books')
        ->and($merged->get('Book')->translations)->toBe(['Livre'])
        ->and($merged->get('Book')->flags)->toBe(['php-format', 'fuzzy'])
        ->and($merged->get('Book')->translatorComments)->toBe(['t1', 't2'])
        ->and($merged->get('Only base')->obsolete)->toBeTrue()
        ->and($merged->has('Only other'))->toBeTrue()
        ->and($merged->has("x\x04Ctx"))->toBeTrue()
        ->and($merged->has("y\x04Ctx"))->toBeTrue();
});

it('keeps the plural of the base, and an active message when only one side is obsolete', function (): void {
    $base = mergeCatalog([mergeMessage('Book', ['a', 'b'], plural: 'Books', obsolete: true), mergeMessage('Page', ['p'])]);
    $other = mergeCatalog([mergeMessage('Book'), mergeMessage('Page', plural: 'Pages', obsolete: true)]);

    $merged = (new CatalogMerger)->mergeAll($base, $other);

    expect($merged->get('Book')->entry->plural)->toBe('Books')
        ->and($merged->get('Book')->translations)->toBe(['a', 'b'])
        ->and($merged->get('Book')->obsolete)->toBeFalse()
        ->and($merged->get('Page')->entry->plural)->toBe('Pages')
        ->and($merged->get('Page')->obsolete)->toBeFalse();
});

it('returns the base as it is when there is nothing to add', function (): void {
    $base = mergeCatalog([mergeMessage('A', ['a'])]);

    expect((new CatalogMerger)->mergeAll($base))->toEqual($base);
});

it('is idempotent and order independent when merging templates', function (): void {
    $one = mergeCatalog([mergeMessage('A', references: ['a.php:1']), mergeMessage('B', references: ['b.php:1'])]);
    $two = mergeCatalog([mergeMessage('B', references: ['b.php:2']), mergeMessage('C')]);
    $merger = new CatalogMerger;

    $both = $merger->mergeAll($one, $two);

    expect($merger->mergeAll($both, $two))->toEqual($both)
        ->and(mergeText($merger->mergeAll($two, $one)))->toBe(mergeText($both));
});

it('merges 20 000 messages in linear time', function (): void {
    $count = 20000;
    $existing = [];
    $fresh = [];

    for ($i = 0; $i < $count; $i++) {
        $existing[] = mergeMessage('Message '.$i, ['Traduction '.$i]);
        $fresh[] = mergeMessage('Message '.($i + $count / 2), references: ['src/file.php:'.$i]);
    }

    $existing = mergeCatalog($existing);
    $fresh = mergeCatalog($fresh);
    $merger = new CatalogMerger;

    $start = hrtime(true);
    $merged = $merger->merge($existing, $fresh);
    $union = $merger->mergeAll($existing, $fresh);
    $seconds = (hrtime(true) - $start) / 1e9;

    expect($merged->count())->toBe($count + $count / 2)
        ->and($union->count())->toBe($count + $count / 2)
        ->and($seconds)->toBeLessThan(5.0);
});
