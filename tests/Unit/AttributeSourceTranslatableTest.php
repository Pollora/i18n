<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tests\Fixtures\Scan\Label;
use Pollora\I18n\Tests\Fixtures\Scan\PostType;
use Pollora\I18n\Tests\Fixtures\Scan\Tags;
use Pollora\I18n\Tooling\AttributeExtraction;
use Pollora\I18n\Tooling\AttributeSource;
use Pollora\I18n\Tooling\CatalogEntry;

const TRANSLATABLE_FIXTURES = __DIR__.'/../Fixtures/Translatable';

/**
 * What the extraction found in one fixture: "msgctxt|msgid" of each entry, by text domain.
 *
 * @return array<string, list<string>>
 */
function translatableSourceKeys(AttributeExtraction $extraction): array
{
    $keys = [];

    foreach ($extraction->entries as $domain => $entries) {
        $keys[$domain] = array_map(static fn (CatalogEntry $entry): string => ($entry->msgctxt ?? '').'|'.$entry->msgid, $entries);
    }

    return $keys;
}

/**
 * @return array<string, CatalogEntry> The entries of a domain by message id
 */
function translatableSourceEntries(AttributeExtraction $extraction, string $domain): array
{
    return array_column($extraction->entriesFor($domain), null, 'msgid');
}

beforeEach(function (): void {
    TranslatableRegistry::flush();
    $this->directory = sys_get_temp_dir().'/pollora-i18n-translatable-'.bin2hex(random_bytes(4));
    mkdir($this->directory);
});

afterEach(function (): void {
    TranslatableRegistry::flush();

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->directory);
});

it('extracts a Translatable written with an alias, whatever the attribute and the depth', function (): void {
    $file = TRANSLATABLE_FIXTURES.'/translatable-alias.php';
    $extraction = (new AttributeSource)->extract([$file], 'default');
    $entries = translatableSourceEntries($extraction, 'default');

    expect($extraction->diagnostics)->toBe([])
        ->and(array_keys($entries))->toBe(['Deep', 'Direct', 'Eight', 'Joined in two', 'Seven', 'Written by', 'Named text'])
        ->and($entries['Written by']->comments)->toBe(['Attribute AdminCols › author.title', 'Attribute AdminCols › k'])
        ->and($entries['Written by']->references)->toBe([$file.':11', $file.':21'])
        ->and($entries['Deep']->comments)->toBe(['Attribute AdminCols › a.b.c.d.0'])
        ->and($entries['Seven']->comments)->toBe(['Attribute AdminCols › a.b.c.d.7'])
        ->and($entries['Eight']->comments)->toBe(['Attribute AdminCols › a.b.c.d.8'])
        ->and($entries['Joined in two']->comments)->toBe(['Attribute AdminCols › a.b.c.d.x'])
        ->and($entries['Direct']->comments)->toBe(['Attribute AdminCols']);
});

it('takes the domain of the Translatable, else of the attribute, else the default', function (): void {
    $extraction = (new AttributeSource)->extract([TRANSLATABLE_FIXTURES.'/translatable-alias.php'], 'default');

    expect(translatableSourceKeys($extraction))->toBe([
        'default' => ['|Deep', '|Direct', '|Eight', '|Joined in two', '|Seven', '|Written by', 'named|Named text'],
        'other' => ['|Second tab'],
        'tabs' => ['|First tab'],
        'theme' => ['column|Published'],
    ]);
});

it('reads the text, the domain and the context by name, and a context of the extraction only when the string has none', function (): void {
    $extraction = (new AttributeSource)->extract([TRANSLATABLE_FIXTURES.'/translatable-alias.php'], 'default', 'Columns');
    $keys = translatableSourceKeys($extraction);

    expect($keys['tabs'])->toBe(['Columns|First tab'])
        ->and($keys['theme'])->toBe(['column|Published'])
        ->and($keys['default'])->toContain('Columns|Deep', 'named|Named text');
});

it('recognizes the class through its full name, its namespace, a namespace alias and any case', function (): void {
    $extraction = (new AttributeSource)->extract([TRANSLATABLE_FIXTURES.'/translatable-fullname.php'], 'default');

    expect($extraction->diagnostics)->toBe([])
        ->and(array_keys(translatableSourceEntries($extraction, 'default')))->toBe(['Fully qualified', 'Imported', 'Other case', 'Relative to the namespace', 'Through a namespace alias']);
});

it('reports what is not a literal, empty or unusable, with its line, and keeps the rest', function (): void {
    $file = TRANSLATABLE_FIXTURES.'/translatable-invalid.php';
    $extraction = (new AttributeSource)->extract([$file], 'default');

    expect($extraction->diagnostics)->toBe([
        $file.':10: The text of a Translatable in #[AdminCols] is not a literal string: it was skipped.',
        $file.':11: The text of a Translatable in #[AdminCols] is not a literal string: it was skipped.',
        $file.':12: The text of a Translatable in #[AdminCols] is not a literal string: it was skipped.',
        $file.':13: A Translatable in #[AdminCols] has an empty text: it was skipped.',
        $file.':14: The text of a Translatable in #[AdminCols] is not a literal string: it was skipped.',
        $file.':15: The text of a Translatable in #[AdminCols] is not a literal string: it was skipped.',
        $file.':16: The domain of a Translatable in #[AdminCols] is not a literal string: it was skipped.',
        $file.':17: The context of a Translatable in #[AdminCols] is not a literal string: it was skipped.',
        $file.':21: The text domain of #[AdminCols] is not a literal string: a Translatable in it was skipped.',
    ])
        ->and(translatableSourceKeys($extraction))->toBe(['default' => ['|Fine'], 'kept' => ['|Kept domain']]);
});

it('does not extract a class named Translatable that is not the one of the package', function (): void {
    $extraction = (new AttributeSource)->extract([TRANSLATABLE_FIXTURES.'/translatable-lookalike.php'], 'default');

    expect($extraction->entries)->toBe([])->and($extraction->diagnostics)->toBe([])->and($extraction->filesParsed)->toBe(1);
});

it('does not extract a Translatable written outside an attribute', function (): void {
    $extraction = (new AttributeSource)->extract([TRANSLATABLE_FIXTURES.'/translatable-outside.php'], 'default');

    expect(translatableSourceKeys($extraction))->toBe(['default' => ['|Inside the attribute']])
        ->and($extraction->diagnostics)->toBe([]);
});

it('leaves a Translatable to its own rule inside a declared field, and uses the declared text domain', function (): void {
    TranslatableRegistry::register(PostType::class, ['singular', 'plural']);
    TranslatableRegistry::register(Label::class, ['value']);
    TranslatableRegistry::register(Tags::class, ['names']);
    $file = TRANSLATABLE_FIXTURES.'/translatable-registered.php';

    $extraction = (new AttributeSource)->extract([$file], 'default');

    expect(translatableSourceKeys($extraction))->toBe(['books' => ['|Book', '|Fiction'], 'default' => ['|Label text'], 'own' => ['|Essay']])
        ->and(translatableSourceEntries($extraction, 'books')['Fiction']->comments)->toBe(['Attribute Tags › 0'])
        ->and($extraction->diagnostics)->toBe([
            $file.':16: The text domain of #[Tags] is not a literal string: a Translatable in it was skipped.',
            $file.':16: The text domain of #[Tags] is not a literal string: its strings were skipped.',
        ]);
});

it('extracts a Translatable even when no class is declared', function (): void {
    $extraction = (new AttributeSource)->extract([TRANSLATABLE_FIXTURES.'/translatable-alias.php'], 'default');

    expect($extraction->entriesFor('default'))->not->toBe([])->and($extraction->filesExamined)->toBe(1);
});

it('names the key of a Translatable without a literal key with a question mark', function (): void {
    file_put_contents($this->directory.'/a.php', "<?php\nuse Pollora\\I18n\\Runtime\\Translatable as T;\n#[A([KEY => new T('Dynamic key'), 'a' => ['b' => new T('Nested')]])]\nclass X {}\n");

    $entries = translatableSourceEntries((new AttributeSource)->extract([$this->directory], 'default'), 'default');

    expect($entries['Dynamic key']->comments)->toBe(['Attribute A › ?'])
        ->and($entries['Nested']->comments)->toBe(['Attribute A › a.b']);
});

it('parses a file only when it has an attribute and names a declared class or Translatable', function (): void {
    TranslatableRegistry::register(Label::class, ['value']);
    $code = "<?php\n%s\nclass C%d {}\n";

    foreach (['#[Deprecated] and nothing else', 'no attribute but a Translatable and a Label', '#[Deprecated] a TranslatableRegistry mention', '#[Deprecated] mentions Label', '#[A] a Translatable mention', '#[A] new TRANSLATABLE'] as $index => $line) {
        file_put_contents($this->directory.'/f'.$index.'.php', sprintf($code, '// '.$line, $index));
    }

    $extraction = (new AttributeSource)->extract([$this->directory], 'default');

    expect($extraction->filesExamined)->toBe(3);
});

it('does not parse again a file with a Translatable that did not change', function (): void {
    $cache = $this->directory.'/cache/attributes.php';
    $source = new AttributeSource($cache);

    $first = $source->extract([TRANSLATABLE_FIXTURES], 'default');
    $second = $source->extract([TRANSLATABLE_FIXTURES], 'default');
    $third = (new AttributeSource($cache))->extract([TRANSLATABLE_FIXTURES], 'other');

    expect($first->filesParsed)->toBe(6)
        ->and($second->filesParsed)->toBe(0)
        ->and($second->filesExamined)->toBe(6)
        ->and($second->entries)->toEqual($first->entries)
        ->and($second->diagnostics)->toBe($first->diagnostics)
        ->and($third->filesParsed)->toBe(0)
        ->and($third->entriesFor('other'))->not->toBe([])
        ->and($third->entriesFor('default'))->toBe([]);
});

it('reads again the file of a cache written before Translatable existed', function (): void {
    $cache = $this->directory.'/attributes.php';
    $file = TRANSLATABLE_FIXTURES.'/translatable-fullname.php';
    (new AttributeSource($cache))->extract([$file], 'default');

    $data = include $cache;
    unset($data['files'][$file]['marked']);
    file_put_contents($cache, '<?php return '.var_export($data, true).';');
    $afterOldEntry = (new AttributeSource($cache))->extract([$file], 'default');

    $data['version'] = 1;
    file_put_contents($cache, '<?php return '.var_export($data, true).';');

    expect($afterOldEntry->filesParsed)->toBe(1)
        ->and((new AttributeSource($cache))->extract([$file], 'default')->filesParsed)->toBe(1);
});

it('stays fast with Translatable: 300 files with one, 300 without, cold then warm', function (): void {
    $with = "<?php\nnamespace N%1\$d;\nuse Pollora\\I18n\\Runtime\\Translatable as T;\n#[Cols(['a' => new T('String %1\$d')])]\nclass C%1\$d {}\n";

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
