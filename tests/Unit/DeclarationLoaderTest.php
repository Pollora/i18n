<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tooling\DeclarationLoader;
use Pollora\I18n\Tooling\DeclarationLocator;
use Pollora\I18n\Tooling\InvalidDeclarationException;

/**
 * @param  array<string, string>  $files
 */
function declarationWrite(string $directory, array $files): void
{
    foreach ($files as $path => $contents) {
        is_dir(dirname($directory.'/'.$path)) || mkdir(dirname($directory.'/'.$path), 0777, true);
        file_put_contents($directory.'/'.$path, $contents);
    }
}

beforeEach(function (): void {
    TranslatableRegistry::flush();
    $this->directory = sys_get_temp_dir().'/pollora-i18n-declarations-'.bin2hex(random_bytes(4));
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

it('feeds the registry from a declaration file', function (): void {
    $count = (new DeclarationLoader)->loadFile(__DIR__.'/../Fixtures/Declarations/valid.php');

    expect($count)->toBe(2)
        ->and(TranslatableRegistry::fieldsFor('Acme\Book'))->toBe(['singular', 'plural'])
        ->and(TranslatableRegistry::fieldsFor('Acme\Label'))->toBe(['value']);
});

it('loads several files and adds to what is declared', function (): void {
    TranslatableRegistry::register('Acme\Book', ['description']);
    declarationWrite($this->directory, ['b.php' => "<?php\nreturn ['Acme\\\\Book' => ['extra']];\n"]);

    $count = (new DeclarationLoader)->loadFiles([__DIR__.'/../Fixtures/Declarations/valid.php', $this->directory.'/b.php']);

    expect($count)->toBe(3)->and(TranslatableRegistry::fieldsFor('Acme\Book'))->toBe(['description', 'singular', 'plural', 'extra']);
});

it('refuses a malformed declaration file with its path, and declares nothing from it', function (string $code, string $message): void {
    declarationWrite($this->directory, ['i18n.php' => $code]);
    $path = $this->directory.'/i18n.php';

    expect(fn () => (new DeclarationLoader)->loadFile($path))->toThrow(InvalidDeclarationException::class, $path)
        ->and(fn () => (new DeclarationLoader)->loadFile($path))->toThrow(InvalidDeclarationException::class, $message)
        ->and(TranslatableRegistry::all())->toBe([]);
})->with([
    'not an array' => ["<?php\nreturn 'x';", 'must return an array'],
    'numeric key' => ["<?php\nreturn [['a']];", 'not a class name'],
    'empty class name' => ["<?php\nreturn ['' => ['a']];", 'not a class name'],
    'fields not an array' => ["<?php\nreturn ['A' => 'a'];", 'array of non-empty strings'],
    'no field' => ["<?php\nreturn ['A' => []];", 'array of non-empty strings'],
    'keyed fields' => ["<?php\nreturn ['A' => ['x' => 'a']];", 'array of non-empty strings'],
    'non-string field' => ["<?php\nreturn ['A' => [1]];", 'array of non-empty strings'],
    'empty field' => ["<?php\nreturn ['A' => ['']];", 'array of non-empty strings'],
    'invalid PHP' => ["<?php\nreturn [;", 'failed to load'],
    'throws' => ["<?php\nthrow new LogicException('boom');", 'boom'],
]);

it('checks the whole file before declaring any of it', function (): void {
    declarationWrite($this->directory, ['i18n.php' => "<?php\nreturn ['Good' => ['a'], 'Bad' => []];"]);

    expect(fn () => (new DeclarationLoader)->loadFile($this->directory.'/i18n.php'))->toThrow(InvalidDeclarationException::class)
        ->and(TranslatableRegistry::fieldsFor('Good'))->toBe([]);
});

it('names a file that does not exist', function (): void {
    expect(fn () => (new DeclarationLoader)->loadFile($this->directory.'/none.php'))->toThrow(InvalidDeclarationException::class, 'none.php');
});

it('loads what the installed packages announce', function (): void {
    declarationWrite($this->directory, [
        'vendor/composer/installed.json' => json_encode(['packages' => [
            ['name' => 'acme/attributes', 'install-path' => '../acme/attributes', 'extra' => ['pollora' => ['i18n' => 'resources/i18n.php']]],
            ['name' => 'acme/other', 'install-path' => '../acme/other', 'extra' => ['branch-alias' => []]],
            ['name' => 'acme/meta', 'install-path' => '../acme/meta'],
        ]], JSON_THROW_ON_ERROR),
        'vendor/acme/attributes/resources/i18n.php' => "<?php\nreturn ['Acme\\\\Book' => ['singular']];\n",
    ]);

    $count = (new DeclarationLoader)->loadInstalled($this->directory.'/vendor/composer/installed.json');

    expect($count)->toBe(1)->and(TranslatableRegistry::fieldsFor('Acme\Book'))->toBe(['singular']);
});

it('locates files in the Composer 1 format, with the default install path', function (): void {
    declarationWrite($this->directory, [
        'vendor/composer/installed.json' => json_encode([
            ['name' => 'acme/old', 'extra' => ['pollora' => ['i18n' => 'i18n.php']]],
            'not a package',
            ['extra' => ['pollora' => ['i18n' => 'nameless.php']], 'install-path' => '../x'],
        ], JSON_THROW_ON_ERROR),
    ]);

    expect((new DeclarationLocator)->locate($this->directory.'/vendor/composer/installed.json'))
        ->toBe([$this->directory.'/vendor/acme/old/i18n.php', $this->directory.'/vendor/x/nameless.php']);
});

it('resolves ./ and .. in install paths', function (): void {
    declarationWrite($this->directory, [
        'vendor/composer/installed.json' => json_encode(['packages' => [
            ['name' => 'acme/a', 'install-path' => '../../packages/./a', 'extra' => ['pollora' => ['i18n' => 'dir/./i18n.php']]],
        ]], JSON_THROW_ON_ERROR),
    ]);

    expect((new DeclarationLocator)->locate($this->directory.'/vendor/composer/installed.json'))->toBe([$this->directory.'/packages/a/dir/i18n.php']);
});

it('refuses an unusable installed.json with its path', function (string $contents, string $message): void {
    declarationWrite($this->directory, ['installed.json' => $contents]);
    $path = $this->directory.'/installed.json';

    expect(fn () => (new DeclarationLocator)->locate($path))->toThrow(InvalidDeclarationException::class, $message);
})->with([
    'not JSON' => ['{nope', 'is not valid Composer JSON'],
    'a scalar' => ['3', 'is not valid Composer JSON'],
    'absolute path' => ['{"packages":[{"name":"a/b","extra":{"pollora":{"i18n":"/etc/i18n.php"}}}]}', 'a/b must be a path relative'],
    'parent path' => ['{"packages":[{"name":"a/b","extra":{"pollora":{"i18n":"../i18n.php"}}}]}', 'a/b must be a path relative'],
    'not a string' => ['{"packages":[{"name":"a/b","extra":{"pollora":{"i18n":3}}}]}', 'a/b must be a path relative'],
    'empty' => ['{"packages":[{"name":"a/b","extra":{"pollora":{"i18n":""}}}]}', 'a/b must be a path relative'],
    'nameless package' => ['{"packages":[{"extra":{"pollora":{"i18n":"../x"}}}]}', 'a package must be a path relative'],
]);

it('names an installed.json that is missing', function (): void {
    expect(fn () => (new DeclarationLocator)->locate($this->directory.'/nope.json'))->toThrow(InvalidDeclarationException::class, 'nope.json');
});
