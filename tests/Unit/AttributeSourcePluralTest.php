<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tests\Fixtures\Scan\PostType;
use Pollora\I18n\Tooling\AttributeExtraction;
use Pollora\I18n\Tooling\AttributeSource;
use Pollora\I18n\Tooling\CatalogEntry;

const EXTRACT_PLURAL_FIXTURES = __DIR__.'/../Fixtures/ExtractPlural';

/**
 * What the extraction found, one array per entry: domain, msgctxt, msgid, plural, comments, then the references without their directory.
 *
 * @return list<array{string, string|null, string, string|null, list<string>, list<string>}>
 */
function extractPluralSummary(AttributeExtraction $extraction): array
{
    $summary = [];

    foreach ($extraction->entries as $domain => $entries) {
        foreach ($entries as $entry) {
            $summary[] = [(string) $domain, $entry->msgctxt, $entry->msgid, $entry->plural, $entry->comments, array_map(extractPluralRelative(...), $entry->references)];
        }
    }

    return $summary;
}

function extractPluralRelative(string $text): string
{
    return str_replace(EXTRACT_PLURAL_FIXTURES.'/', '', $text);
}

/**
 * @return list<string>
 */
function extractPluralDiagnostics(AttributeExtraction $extraction): array
{
    return array_map(extractPluralRelative(...), $extraction->diagnostics);
}

/**
 * The entries of a domain by message id.
 *
 * @return array<string, CatalogEntry>
 */
function extractPluralEntries(AttributeExtraction $extraction, string $domain): array
{
    return array_column($extraction->entriesFor($domain), null, 'msgid');
}

beforeEach(function (): void {
    TranslatableRegistry::flush();
    $this->directory = sys_get_temp_dir().'/pollora-i18n-extract-plural-'.bin2hex(random_bytes(4));
    mkdir($this->directory);
});

afterEach(function (): void {
    TranslatableRegistry::flush();

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->directory);
});

it('extracts a TranslatablePlural written positionally or by name, with its domain, context and comment', function (): void {
    $extraction = (new AttributeSource)->extract([EXTRACT_PLURAL_FIXTURES.'/plural-forms.php'], 'default');
    $summary = extractPluralSummary($extraction);

    expect($extraction->diagnostics)->toBe([])
        ->and($summary)->toContain(
            ['default', null, 'One book', '%d books', ['Attribute Counts › books'], ['plural-forms.php:11']],
            ['default', 'cinema', 'One film', '%d films', ['Number of films', 'Attribute Counts › films.label'], ['plural-forms.php:11']],
            ['own', 'ctx', 'One own', '%d owns', ['Has its own domain', 'Attribute Counts › b'], ['plural-forms.php:14']],
            ['shop', null, 'One item', '%d items', ['Attribute Counts › a'], ['plural-forms.php:14']],
            ['shop', null, 'One null', '%d nulls', ['Attribute Counts › c'], ['plural-forms.php:14']],
        );
});

it('recognizes the class through its name, an alias and any case', function (): void {
    $entries = extractPluralEntries((new AttributeSource)->extract([EXTRACT_PLURAL_FIXTURES.'/plural-forms.php'], 'default'), 'default');

    expect(array_map(static fn (CatalogEntry $entry): ?string => $entry->plural, [$entries['Imported by its name'], $entries['Imported'], $entries['Other case']]))
        ->toBe(['Imported by their names', 'Importeds', 'Other cases']);
});

it('recognizes the class through its full name, its namespace and a namespace alias', function (): void {
    $file = $this->directory.'/a.php';
    file_put_contents($file, "<?php\nnamespace Pollora\\I18n;\nuse Pollora\\I18n\\Runtime as R;\n#[A([new \\Pollora\\I18n\\Runtime\\TranslatablePlural('Full', 'Fulls'), new Runtime\\TranslatablePlural('Relative', 'Relatives'), new R\\TranslatablePlural('Aliased', 'Aliaseds'), new \\pollora\\i18n\\runtime\\translatableplural('Lower', 'Lowers')])]\nclass A1 {}\n");

    $extraction = (new AttributeSource)->extract([$file], 'default');

    expect($extraction->diagnostics)->toBe([])
        ->and(array_map(static fn (CatalogEntry $entry): string => $entry->msgid.'|'.$entry->plural, $extraction->entriesFor('default')))->toBe(['Aliased|Aliaseds', 'Full|Fulls', 'Lower|Lowers', 'Relative|Relatives']);
});

it('joins literals, names the keys like for a Translatable and ignores an empty comment', function (): void {
    $entries = extractPluralEntries((new AttributeSource)->extract([EXTRACT_PLURAL_FIXTURES.'/plural-forms.php'], 'default'), 'default');

    expect($entries['One joined']->plural)->toBe('%d joined')
        ->and($entries['One joined']->comments)->toBe(['Joined comment', 'Attribute Counts › joined'])
        ->and($entries['Seven']->comments)->toBe(['Attribute Counts › 7.0'])
        ->and($entries['Eight']->comments)->toBe(['Attribute Counts › 8'])
        ->and($entries['Blank']->comments)->toBe(['Attribute Counts › blank']);
});

it('puts the comment of a Translatable before the provenance, in the fourth argument too', function (): void {
    $file = $this->directory.'/a.php';
    file_put_contents($file, "<?php\nuse Pollora\\I18n\\Runtime\\Translatable as T;\n#[A(['x' => new T('Written by', null, null, 'Byline of an article'), 'y' => new T(text: 'Named', comment: 'Named note')])]\nclass X {}\n");

    $entries = extractPluralEntries((new AttributeSource)->extract([$file], 'default'), 'default');

    expect($entries['Written by']->comments)->toBe(['Byline of an article', 'Attribute A › x'])
        ->and($entries['Named']->comments)->toBe(['Named note', 'Attribute A › y'])
        ->and($entries['Written by']->plural)->toBeNull();
});

it('reports what is not a literal or is empty, with its line, and keeps the string when only its comment is wrong', function (): void {
    $extraction = (new AttributeSource)->extract([EXTRACT_PLURAL_FIXTURES.'/plural-invalid.php'], 'default');

    expect(extractPluralDiagnostics($extraction))->toBe([
        'plural-invalid.php:11: The singular of a TranslatablePlural in #[Counts] is not a literal string: it was skipped.',
        'plural-invalid.php:12: The plural of a TranslatablePlural in #[Counts] is not a literal string: it was skipped.',
        'plural-invalid.php:13: A TranslatablePlural in #[Counts] has an empty singular: it was skipped.',
        'plural-invalid.php:14: A TranslatablePlural in #[Counts] has an empty plural: it was skipped.',
        'plural-invalid.php:15: The plural of a TranslatablePlural in #[Counts] is not a literal string: it was skipped.',
        'plural-invalid.php:16: The singular of a TranslatablePlural in #[Counts] is not a literal string: it was skipped.',
        'plural-invalid.php:17: The domain of a TranslatablePlural in #[Counts] is not a literal string: it was skipped.',
        'plural-invalid.php:18: The context of a TranslatablePlural in #[Counts] is not a literal string: it was skipped.',
        'plural-invalid.php:19: A TranslatablePlural in #[Counts] has an empty context: it was skipped.',
        'plural-invalid.php:20: A Translatable in #[Counts] has an empty context: it was skipped.',
        'plural-invalid.php:21: The comment of a TranslatablePlural in #[Counts] is not a literal string: it was left out.',
        'plural-invalid.php:22: The comment of a Translatable in #[Counts] is not a literal string: it was left out.',
        'plural-invalid.php:26: The text domain of #[Counts] is not a literal string: a TranslatablePlural in it was skipped.',
    ])
        ->and(extractPluralSummary($extraction))->toBe([
            ['default', null, 'Fine', 'Fines', ['Attribute Counts › fine'], ['plural-invalid.php:23']],
            ['default', null, 'Kept one', 'Kept many', ['Attribute Counts › comment'], ['plural-invalid.php:21']],
            ['default', null, 'Kept text', null, ['Attribute Counts › text comment'], ['plural-invalid.php:22']],
            ['kept', null, 'Kept domain', 'Kept domains', ['Attribute Counts › explicit'], ['plural-invalid.php:26']],
        ]);
});

it('writes a string found twice once, with every reference, every comment and the same plural', function (): void {
    $extraction = (new AttributeSource)->extract([EXTRACT_PLURAL_FIXTURES.'/plural-duplicates.php'], 'default');
    $entries = extractPluralEntries($extraction, 'default');

    expect($entries['One book']->plural)->toBe('%d books')
        ->and(array_map(extractPluralRelative(...), $entries['One book']->references))->toBe(['plural-duplicates.php:11', 'plural-duplicates.php:12'])
        ->and($entries['One book']->comments)->toBe(['Alpha note', 'Zebra note', 'Attribute Counts › a', 'Attribute Counts › b', 'Attribute Counts › c']);
});

it('reports a conflict of plural forms, keeps the first occurrence and never stays silent', function (): void {
    $extraction = (new AttributeSource)->extract([EXTRACT_PLURAL_FIXTURES.'/plural-duplicates.php'], 'default');
    $film = extractPluralEntries($extraction, 'default')['One film'];

    expect(extractPluralDiagnostics($extraction))->toBe([
        'plural-duplicates.php:16: Conflict: "One film" is written with the plural form "%d movies" here but with the plural form "%d films" at plural-duplicates.php:15: the first one was kept and this one left out.',
        'plural-duplicates.php:17: Conflict: "One film" is written with no plural form here but with the plural form "%d films" at plural-duplicates.php:15: the first one was kept and this one left out.',
        'plural-duplicates.php:22: Conflict: "Shelf" is written with the plural form "Shelves" here but with no plural form at plural-duplicates.php:21: the first one was kept and this one left out.',
    ])
        ->and($film->plural)->toBe('%d films')
        ->and(array_map(extractPluralRelative(...), $film->references))->toBe(['plural-duplicates.php:15', 'plural-duplicates.php:18'])
        ->and(extractPluralEntries($extraction, 'default')['Shelf']->plural)->toBeNull();
});

it('keeps apart the strings that differ by context or domain, with no conflict', function (): void {
    $extraction = (new AttributeSource)->extract([EXTRACT_PLURAL_FIXTURES.'/plural-duplicates.php'], 'default');

    expect(array_values(array_filter(extractPluralSummary($extraction), static fn (array $entry): bool => $entry[2] === 'Same')))->toBe([
        ['default', null, 'Same', 'Plural', ['Attribute Counts › a'], ['plural-duplicates.php:25']],
        ['default', 'context', 'Same', 'Another plural', ['Attribute Counts › b'], ['plural-duplicates.php:25']],
        ['other', null, 'Same', 'Third plural', ['Attribute Counts › c'], ['plural-duplicates.php:25']],
    ]);
});

it('keeps the first occurrence whatever the order the files are read in', function (): void {
    foreach (['b' => 'Second', 'a' => 'First'] as $name => $plural) {
        file_put_contents($this->directory.'/'.$name.'.php', "<?php\nuse Pollora\\I18n\\Runtime\\TranslatablePlural as P;\n#[A([new P('One', '".$plural."')])]\nclass C".$name." {}\n");
    }

    $extraction = (new AttributeSource(null, $this->directory))->extract([$this->directory], 'default');

    expect(extractPluralEntries($extraction, 'default')['One']->plural)->toBe('First')
        ->and($extraction->diagnostics)->toBe(['b.php:3: Conflict: "One" is written with the plural form "Second" here but with the plural form "First" at a.php:3: the first one was kept and this one left out.']);
});

it('names the context of a conflict', function (): void {
    file_put_contents($this->directory.'/a.php', "<?php\nuse Pollora\\I18n\\Runtime\\TranslatablePlural as P;\n#[A([new P('One', 'Many', null, 'ctx'), new P('One', 'Other', null, 'ctx')])]\nclass A1 {}\n");

    $extraction = (new AttributeSource(null, $this->directory))->extract([$this->directory], 'default');

    expect($extraction->diagnostics)->toBe(['a.php:3: Conflict: "One" (context "ctx") is written with the plural form "Other" here but with the plural form "Many" at a.php:3: the first one was kept and this one left out.']);
});

it('gives a plural inside a declared field to its own rule, not to the field', function (): void {
    TranslatableRegistry::register(PostType::class, ['singular', 'plural']);
    file_put_contents($this->directory.'/a.php', "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\PostType;\nuse Pollora\\I18n\\Runtime\\TranslatablePlural as P;\n#[PostType('Book', plural: [new P('One', 'Many', comment: 'In a field')], textDomain: 'books')]\nclass A1 {}\n");

    $extraction = (new AttributeSource(null, $this->directory))->extract([$this->directory], 'default');

    expect($extraction->diagnostics)->toBe([])
        ->and(array_map(static fn (array $entry): array => [$entry[0], $entry[2], $entry[3], $entry[4]], extractPluralSummary($extraction)))->toBe([
            ['books', 'Book', null, ['Attribute PostType::singular']],
            ['books', 'One', 'Many', ['In a field', 'Attribute PostType › 0']],
        ]);
});

it('does not extract another class named TranslatablePlural, nor one written outside an attribute', function (): void {
    file_put_contents($this->directory.'/a.php', "<?php\nuse Other\\Runtime\\TranslatablePlural;\nuse Pollora\\I18n\\Runtime\\TranslatablePlural as P;\n#[A([new TranslatablePlural('Elsewhere', 'Elsewheres'), new Runtime\\TranslatablePlural('Relative', 'Relatives')])]\nclass A1 { public function run(): array { return [new P('Outside', 'Outsides')]; } }\nconst X = new P('Constant', 'Constants');\n");

    $extraction = (new AttributeSource(null, $this->directory))->extract([$this->directory], 'default');

    expect($extraction->entries)->toBe([])->and($extraction->diagnostics)->toBe([])->and($extraction->filesParsed)->toBe(1);
});

it('lets a file through the pre-filter when it names TranslatablePlural, and still skips the others', function (): void {
    $code = "<?php\n%s\nclass C%d {}\n";
    $lines = [
        '#[A] new TranslatablePlural',
        '#[A] new TRANSLATABLEPLURAL',
        '#[A] use X\\TranslatablePlural as P',
        '#[A] a Translatable mention',
        '#[A] MyTranslatablePlurals and Translatables',
        'TranslatablePlural without an attribute',
        '#[A] nothing at all',
    ];

    foreach ($lines as $index => $line) {
        file_put_contents($this->directory.'/f'.$index.'.php', sprintf($code, '// '.$line, $index));
    }

    expect((new AttributeSource)->extract([$this->directory], 'default')->filesExamined)->toBe(4);
});

it('does not parse again a file with a plural that did not change, and keeps its comments and conflicts', function (): void {
    $cache = $this->directory.'/cache/attributes.php';

    $first = (new AttributeSource($cache))->extract([EXTRACT_PLURAL_FIXTURES], 'default');
    $second = (new AttributeSource($cache))->extract([EXTRACT_PLURAL_FIXTURES], 'default');
    $third = (new AttributeSource($cache))->extract([EXTRACT_PLURAL_FIXTURES], 'other');

    expect($first->filesParsed)->toBe(3)
        ->and($second->filesParsed)->toBe(0)
        ->and($second->entries)->toEqual($first->entries)
        ->and($second->diagnostics)->toBe($first->diagnostics)
        ->and($third->filesParsed)->toBe(0)
        ->and($third->entriesFor('other'))->not->toBe([]);
});

it('reads again a cache written before plurals and comments, at version 2', function (): void {
    $cache = $this->directory.'/attributes.php';
    $file = EXTRACT_PLURAL_FIXTURES.'/plural-forms.php';
    (new AttributeSource($cache))->extract([$file], 'default');

    $data = include $cache;
    expect($data['version'])->toBe(3);

    $data['version'] = 2;
    file_put_contents($cache, '<?php return '.var_export($data, true).';');

    expect((new AttributeSource($cache))->extract([$file], 'default')->filesParsed)->toBe(1)
        ->and((new AttributeSource($cache))->extract([$file], 'default')->filesParsed)->toBe(0);
});

it('stays fast with plurals: 300 files with one, 300 without, cold then warm', function (): void {
    $with = "<?php\nnamespace N%1\$d;\nuse Pollora\\I18n\\Runtime\\TranslatablePlural as P;\n#[Cols(['a' => new P('One %1\$d', '%%d of %1\$d', comment: 'Note %1\$d')])]\nclass C%1\$d {}\n";

    for ($i = 0; $i < 300; $i++) {
        file_put_contents($this->directory.'/with'.$i.'.php', sprintf($with, $i));
        file_put_contents($this->directory.'/without'.$i.'.php', "<?php\n#[Deprecated]\nclass G{$i} { public function run(): void {} }\n");
    }

    $source = new AttributeSource($this->directory.'/cache.php');
    $start = hrtime(true);
    $cold = $source->extract([$this->directory], 'default');
    $coldMs = (hrtime(true) - $start) / 1e6;
    $start = hrtime(true);
    $warm = $source->extract([$this->directory], 'default');
    $warmMs = (hrtime(true) - $start) / 1e6;

    expect($cold->filesParsed)->toBe(300)
        ->and($warm->filesParsed)->toBe(0)
        ->and($warm->entriesFor('default'))->toHaveCount(300)
        ->and($coldMs)->toBeLessThan(5000)
        ->and($warmMs)->toBeLessThan(2000);
});
