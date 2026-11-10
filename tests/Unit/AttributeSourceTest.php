<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tests\Fixtures\Scan\Label;
use Pollora\I18n\Tests\Fixtures\Scan\PostType;
use Pollora\I18n\Tests\Fixtures\Scan\Tags;
use Pollora\I18n\Tooling\AttributeExtraction;
use Pollora\I18n\Tooling\AttributeSource;
use Pollora\I18n\Tooling\AttributeSourceException;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\FileWriteException;

const ATTRIBUTE_SOURCE_FIXTURES = __DIR__.'/../Fixtures/Scan';

/**
 * Writes files under the test directory; the keys are relative paths.
 *
 * @param  array<string, string>  $files
 */
function attributeSourceWrite(string $directory, array $files): void
{
    foreach ($files as $path => $contents) {
        is_dir(dirname($directory.'/'.$path)) || mkdir(dirname($directory.'/'.$path), 0777, true);
        file_put_contents($directory.'/'.$path, $contents);
    }
}

/**
 * @return list<string>
 */
function attributeSourceMsgids(AttributeExtraction $extraction, string $domain): array
{
    return array_map(static fn (CatalogEntry $entry): string => $entry->msgid, $extraction->entriesFor($domain));
}

beforeEach(function (): void {
    TranslatableRegistry::flush();
    TranslatableRegistry::register(PostType::class, ['singular', 'plural']);
    TranslatableRegistry::register(Label::class, ['value']);
    TranslatableRegistry::register(Tags::class, ['names']);
    $this->directory = sys_get_temp_dir().'/pollora-i18n-attributes-'.bin2hex(random_bytes(4));
    mkdir($this->directory);
});

afterEach(function (): void {
    TranslatableRegistry::flush();
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->directory);
});

it('extracts positional and named arguments, with the default domain and its own domain', function (): void {
    $extraction = (new AttributeSource)->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default');

    expect($extraction->domains())->toBe(['default', 'theme'])
        ->and(attributeSourceMsgids($extraction, 'theme'))->toBe(['Film', 'Films'])
        ->and(attributeSourceMsgids($extraction, 'default'))->toContain('Book', 'Books', 'Hello', 'World');
});

it('writes where each string came from and which attribute it belongs to', function (): void {
    $file = ATTRIBUTE_SOURCE_FIXTURES.'/Books.php';
    $extraction = (new AttributeSource)->extract([$file], 'default');
    $entries = array_column($extraction->entriesFor('default'), null, 'msgid');

    expect($entries['Book']->comments)->toBe(['Attribute PostType::singular'])
        ->and($entries['Books']->comments)->toBe(['Attribute PostType::plural'])
        ->and($entries['Book']->references)->toBe([$file.':12'])
        ->and($entries['Hello']->references)->toBe([$file.':19', $file.':22'])
        ->and($entries['Book']->msgctxt)->toBeNull();
});

it('reads #[A, B], repeated attributes, properties, anonymous classes, arrays and joined literals', function (): void {
    $extraction = (new AttributeSource)->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default');

    expect(attributeSourceMsgids($extraction, 'default'))->toContain('Hello', 'World', 'Inside', 'One', 'Two', 'Joined in two')
        ->and(attributeSourceMsgids($extraction, 'default'))->not->toContain('Never extracted', '');
});

it('merges the references of a string used twice into one entry', function (): void {
    $extraction = (new AttributeSource)->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default');
    $hellos = array_filter($extraction->entriesFor('default'), static fn (CatalogEntry $entry): bool => $entry->msgid === 'Hello');

    expect($hellos)->toHaveCount(1)
        ->and(array_values($hellos)[0]->references)->toHaveCount(2);
});

it('gives every entry the requested context', function (): void {
    $extraction = (new AttributeSource)->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default', 'Post type');

    expect($extraction->entriesFor('default')[0]->msgctxt)->toBe('Post type');
});

it('reports what it cannot read and extracts the rest', function (): void {
    $file = ATTRIBUTE_SOURCE_FIXTURES.'/Skipped.php';
    $extraction = (new AttributeSource)->extract([$file], 'default');

    expect(attributeSourceMsgids($extraction, 'default'))->toBe(['Fine', 'Kept'])
        ->and($extraction->diagnostics)->toBe([
            $file.':11: The "singular" argument of #[PostType] is not a literal string: it was skipped.',
            $file.':12: The "value" argument of #[Label] is not a literal string: it was skipped.',
            $file.':13: The "names" argument of #[Tags] is not a literal string: it was skipped.',
            $file.':14: The text domain of #[PostType] is not a literal string: its strings were skipped.',
        ]);
});

it('reports a positional argument it cannot match to a parameter', function (): void {
    $class = 'Pollora\I18n\Tests\Fixtures\Scan\Ghost';
    TranslatableRegistry::register($class, ['title']);
    attributeSourceWrite($this->directory, ['a.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Ghost;\n#[Ghost('Title', title: 'Named')]\nclass A {}\n"]);

    $extraction = (new AttributeSource)->extract([$this->directory], 'default');

    expect(attributeSourceMsgids($extraction, 'default'))->toBe(['Named'])
        ->and($extraction->diagnostics)->toHaveCount(1)
        ->and($extraction->diagnostics[0])->toContain('a.php:3: Argument 1 of #[Ghost] cannot be matched');
});

it('reports a file it cannot parse and goes on', function (): void {
    attributeSourceWrite($this->directory, [
        'broken.php' => "<?php\n#[PostType('x')]\nclass { \n",
        'fine.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('Fine')]\nclass B {}\n",
    ]);

    $extraction = (new AttributeSource)->extract([$this->directory], 'default');

    expect(attributeSourceMsgids($extraction, 'default'))->toBe(['Fine'])
        ->and($extraction->diagnostics)->toHaveCount(1)
        ->and($extraction->diagnostics[0])->toContain('broken.php:3: The file could not be parsed');
});

it('ignores a translatable class it does not resolve to', function (): void {
    attributeSourceWrite($this->directory, ['a.php' => "<?php\nnamespace Other;\n#[Label('Not ours')]\nclass A {}\n"]);

    expect((new AttributeSource)->extract([$this->directory], 'default')->entries)->toBe([]);
});

it('does not read vendor, node_modules, .git or Blade views', function (): void {
    $code = "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('%s')]\nclass C {}\n";
    attributeSourceWrite($this->directory, [
        'src/Deep/kept.php' => sprintf($code, 'Kept'),
        'vendor/pkg/a.php' => sprintf($code, 'Vendor'),
        'src/node_modules/a.php' => sprintf($code, 'Node'),
        '.git/a.php' => sprintf($code, 'Git'),
        'views/page.blade.php' => sprintf($code, 'Blade'),
        'notes.txt' => sprintf($code, 'Text'),
    ]);

    $extraction = (new AttributeSource)->extract([$this->directory], 'default');

    expect(attributeSourceMsgids($extraction, 'default'))->toBe(['Kept']);
});

it('reads a path given as a file even in a directory it would skip, and fails on a missing path', function (): void {
    attributeSourceWrite($this->directory, ['vendor/a.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('Explicit')]\nclass D {}\n"]);

    expect(attributeSourceMsgids((new AttributeSource)->extract([$this->directory.'/vendor/a.php'], 'default'), 'default'))->toBe(['Explicit'])
        ->and(fn () => (new AttributeSource)->extract([$this->directory.'/missing'], 'default'))->toThrow(AttributeSourceException::class, 'missing');
});

it('does not parse a file without #[ nor one that names no declared class', function (): void {
    attributeSourceWrite($this->directory, [
        'plain.php' => "<?php\nclass E { public function f() { return [1, 2]; } }\n",
        'other.php' => "<?php\n#[Deprecated('x')]\nclass F {}\n",
    ]);

    $extraction = (new AttributeSource)->extract([$this->directory], 'default');

    expect($extraction->filesExamined)->toBe(0)->and($extraction->filesParsed)->toBe(0);
});

it('does nothing when no class is declared', function (): void {
    TranslatableRegistry::flush();

    $extraction = (new AttributeSource)->extract([ATTRIBUTE_SOURCE_FIXTURES], 'default');

    expect($extraction->entries)->toBe([])->and($extraction->filesExamined)->toBe(0);
});

it('reports a file it cannot read', function (): void {
    attributeSourceWrite($this->directory, ['a.php' => "<?php\n#[Label('x')]\nclass G {}\n"]);
    chmod($this->directory.'/a.php', 0000);

    if (is_readable($this->directory.'/a.php')) {
        $this->markTestSkipped('Permissions are not enforced for this user.');
    }

    set_error_handler(static fn (): bool => true);
    $extraction = (new AttributeSource)->extract([$this->directory], 'default');
    restore_error_handler();

    expect($extraction->diagnostics)->toBe([$this->directory.'/a.php:1: The file could not be read.']);
});

it('writes references relative to the base path', function (): void {
    $extraction = (new AttributeSource(null, ATTRIBUTE_SOURCE_FIXTURES))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php', ATTRIBUTE_SOURCE_FIXTURES.'/Skipped.php'], 'default');
    $book = array_column($extraction->entriesFor('default'), null, 'msgid')['Book'];

    expect($book->references)->toBe(['Books.php:12'])
        ->and($extraction->diagnostics[0])->toStartWith('Skipped.php:');
});

it('does not parse again a file that did not change', function (): void {
    $cache = $this->directory.'/cache/attributes.php';
    $source = new AttributeSource($cache);

    $first = $source->extract([ATTRIBUTE_SOURCE_FIXTURES], 'default');
    $modified = filemtime($cache);
    clearstatcache();
    $second = $source->extract([ATTRIBUTE_SOURCE_FIXTURES], 'default');

    expect($first->filesParsed)->toBeGreaterThanOrEqual(2)
        ->and($second->filesParsed)->toBe(0)
        ->and($second->filesExamined)->toBe($first->filesParsed)
        ->and($second->entries)->toEqual($first->entries)
        ->and($second->diagnostics)->toBe($first->diagnostics)
        ->and(filemtime($cache))->toBe($modified);
});

it('parses again a file whose contents changed, and the cache follows', function (): void {
    $cache = $this->directory.'/attributes.php';
    $code = "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('%s')]\nclass H {}\n";
    attributeSourceWrite($this->directory, ['src/a.php' => sprintf($code, 'Before'), 'src/b.php' => sprintf($code, 'Stays')]);
    $source = new AttributeSource($cache);
    $source->extract([$this->directory.'/src'], 'default');

    file_put_contents($this->directory.'/src/a.php', sprintf($code, 'After'));
    $second = $source->extract([$this->directory.'/src'], 'default');
    unlink($this->directory.'/src/b.php');
    $third = $source->extract([$this->directory.'/src'], 'default');

    expect($second->filesParsed)->toBe(1)
        ->and(attributeSourceMsgids($second, 'default'))->toBe(['After', 'Stays'])
        ->and($third->filesParsed)->toBe(0)
        ->and(file_get_contents($cache))->not->toContain('b.php');
});

it('keeps a cache valid when the default domain changes', function (): void {
    $cache = $this->directory.'/attributes.php';
    (new AttributeSource($cache))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'one');

    $extraction = (new AttributeSource($cache))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'two');

    expect($extraction->filesParsed)->toBe(0)->and($extraction->domains())->toBe(['theme', 'two']);
});

it('throws the cache away when the declared fields change', function (): void {
    $cache = $this->directory.'/attributes.php';
    (new AttributeSource($cache))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default');
    TranslatableRegistry::register(Label::class, ['other']);

    expect((new AttributeSource($cache))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default')->filesParsed)->toBe(1);
});

it('does not depend on the order in which fields were declared', function (): void {
    $cache = $this->directory.'/attributes.php';
    (new AttributeSource($cache))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default');
    TranslatableRegistry::flush();
    TranslatableRegistry::register(Tags::class, ['names']);
    TranslatableRegistry::register(Label::class, ['value']);
    TranslatableRegistry::register(PostType::class, ['plural', 'singular']);

    expect((new AttributeSource($cache))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default')->filesParsed)->toBe(0);
});

it('ignores a cache that is corrupt, outdated or tampered with', function (string $contents): void {
    $cache = $this->directory.'/attributes.php';
    file_put_contents($cache, $contents);

    $extraction = (new AttributeSource($cache))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default');

    expect($extraction->filesParsed)->toBe(1)->and(attributeSourceMsgids($extraction, 'default'))->toContain('Book');
})->with([
    'syntax error' => ['<?php return [;'],
    'not an array' => ['<?php return 3;'],
    'other version' => ["<?php return ['version' => 0, 'fields' => 'x', 'files' => []];"],
    'entry of the wrong shape' => ["<?php return ['version' => 1, 'fields' => 'x', 'files' => ['a' => 'b']];"],
]);

it('ignores a cached entry of the wrong shape for a file', function (): void {
    $cache = $this->directory.'/attributes.php';
    $file = ATTRIBUTE_SOURCE_FIXTURES.'/Books.php';
    (new AttributeSource($cache))->extract([$file], 'default');
    $data = include $cache;
    $data['files'][$file]['records'] = 'garbage';
    file_put_contents($cache, '<?php return '.var_export($data, true).';');

    expect((new AttributeSource($cache))->extract([$file], 'default')->filesParsed)->toBe(1);
});

it('fails with a named exception when the cache cannot be written', function (): void {
    file_put_contents($this->directory.'/blocker', 'a file where a directory should be');
    set_error_handler(static fn (): bool => true);

    try {
        expect(fn () => (new AttributeSource($this->directory.'/blocker/cache.php'))->extract([ATTRIBUTE_SOURCE_FIXTURES.'/Books.php'], 'default'))
            ->toThrow(FileWriteException::class);
    } finally {
        restore_error_handler();
    }
});

it('stays fast: a large tree is scanned at a cold start, then again from the cache', function (): void {
    $code = "<?php\nnamespace N%1\$d;\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('String %1\$d')]\nclass C%1\$d {}\n";

    for ($i = 0; $i < 300; $i++) {
        attributeSourceWrite($this->directory, ['with/f'.$i.'.php' => sprintf($code, $i), 'without/g'.$i.'.php' => "<?php\nclass G{$i} { public function run(): void {} }\n"]);
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

it('reports a positional argument of a class whose autoloading fails, and goes on', function (): void {
    $class = 'Pollora\I18n\Tests\Fixtures\Scan\Exploding';
    $loader = static function (string $name) use ($class): void {
        if ($name === $class) {
            throw new LogicException('The autoloader failed.');
        }
    };
    spl_autoload_register($loader);
    TranslatableRegistry::register($class, ['title']);
    attributeSourceWrite($this->directory, ['a.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Exploding;\n#[Exploding('Title')]\nclass A {}\n"]);

    try {
        $extraction = (new AttributeSource)->extract([$this->directory], 'default');
    } finally {
        spl_autoload_unregister($loader);
    }

    expect($extraction->entries)->toBe([])->and($extraction->diagnostics)->toHaveCount(1);
});
