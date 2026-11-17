<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tests\Fixtures\Scan\Label;
use Pollora\I18n\Tests\Fixtures\Scan\PostType;
use Pollora\I18n\Tests\Fixtures\Scan\Tags;
use Pollora\I18n\Tooling\AttributeSource;
use Pollora\I18n\Tooling\CatalogEntry;

const SOURCE_PREAMBLE = "<?php\nuse Pollora\\I18n\\Runtime\\Translatable as T;\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\PostType;\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Tags;\n";

beforeEach(function (): void {
    TranslatableRegistry::flush();
    TranslatableRegistry::register(PostType::class, ['singular', 'plural']);
    TranslatableRegistry::register(Label::class, ['value']);
    TranslatableRegistry::register(Tags::class, ['names']);
    $this->directory = cliDirectory();
});

afterEach(fn () => TranslatableRegistry::flush());

it('goes on with the next file after one that cannot hold an attribute', function (): void {
    cliWrite($this->directory, [
        'a-plain.php' => "<?php\nclass Plain {}\n",
        'b-plain.php' => "<?php\nclass Other {}\n",
        'c-label.php' => SOURCE_PREAMBLE."#[Label('Found')]\nclass C {}\n",
    ]);

    $extraction = (new AttributeSource)->extract([$this->directory.'/a-plain.php', $this->directory.'/b-plain.php', $this->directory.'/c-label.php'], 'default');

    expect(array_map(static fn (CatalogEntry $entry): string => $entry->msgid, $extraction->entriesFor('default')))->toBe(['Found']);
});

it('keeps one entry per context and message id, and tells apart what a separator keeps apart', function (): void {
    cliWrite($this->directory, [
        'a.php' => SOURCE_PREAMBLE."#[Tags([new T('Add', null, 'verb'), new T('Add', null, 'noun'), new T('Add'), new T('bc', null, 'a'), new T('c', null, 'ab')])]\nclass A {}\n",
    ]);

    $entries = (new AttributeSource)->extract([$this->directory], 'default')->entriesFor('default');
    $keys = array_map(static fn (CatalogEntry $entry): string => $entry->key(), $entries);
    sort($keys);

    expect($keys)->toBe(['Add', "a\x04bc", "ab\x04c", "noun\x04Add", "verb\x04Add"]);
});

it('lists the attributes a string came from once each and in order, whatever the order of the files', function (): void {
    cliWrite($this->directory, [
        'a.php' => SOURCE_PREAMBLE."#[PostType('Same')]\nclass A {}\n",
        'b.php' => SOURCE_PREAMBLE."#[Label('Same')]\nclass B {}\n",
        'c.php' => SOURCE_PREAMBLE."#[PostType('Same')]\nclass C {}\n",
        'd.php' => SOURCE_PREAMBLE."#[Label('Same')]\nclass D {}\n",
    ]);

    $entry = (new AttributeSource($this->directory.'/cache.php'))->extract([$this->directory.'/a.php', $this->directory.'/b.php', $this->directory.'/c.php', $this->directory.'/d.php'], 'default')->entriesFor('default')[0];

    expect($entry->comments)->toBe(['Attribute Label::value', 'Attribute PostType::singular'])
        ->and($entry->references)->toHaveCount(4);
});

it('lists the notes for translators once each, before the attributes', function (): void {
    cliWrite($this->directory, [
        'a.php' => SOURCE_PREAMBLE."#[Tags([new T('Same', null, null, 'Zebra note'), new T('Same', null, null, 'Alpha note'), new T('Same', null, null, 'Zebra note')])]\nclass A {}\n",
    ]);

    $entry = (new AttributeSource)->extract([$this->directory], 'default')->entriesFor('default')[0];

    expect($entry->comments)->toBe(['Alpha note', 'Zebra note', 'Attribute Tags › 0', 'Attribute Tags › 1', 'Attribute Tags › 2']);
});

it('writes the references relative to a base path given with a trailing slash', function (): void {
    cliWrite($this->directory, ['src/a.php' => SOURCE_PREAMBLE."#[Label('Here')]\nclass A {}\n"]);

    $entry = (new AttributeSource(null, $this->directory.'/'))->extract([$this->directory.'/src'], 'default')->entriesFor('default')[0];

    expect($entry->references)->toBe(['src/a.php:6']);
});

it('writes its cache with the files in order and a digest of the declarations', function (): void {
    cliWrite($this->directory, [
        'b.php' => SOURCE_PREAMBLE."#[Label('B')]\nclass B {}\n",
        'a.php' => SOURCE_PREAMBLE."#[Label('A')]\nclass A {}\n",
    ]);
    $cache = $this->directory.'/cache.php';

    (new AttributeSource($cache))->extract([$this->directory.'/b.php', $this->directory.'/a.php'], 'default');

    /** @var array{fields: string, files: array<string, mixed>} $stored */
    $stored = include $cache;

    expect(array_keys($stored['files']))->toBe([$this->directory.'/a.php', $this->directory.'/b.php'])
        ->and($stored['fields'])->toMatch('/^[0-9a-f]{32}$/');
});
