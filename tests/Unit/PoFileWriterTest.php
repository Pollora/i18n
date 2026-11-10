<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Catalog;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\CatalogMessage;
use Pollora\I18n\Tooling\PoFileException;
use Pollora\I18n\Tooling\PoFileWriter;
use Pollora\I18n\Tooling\PoReader;
use Pollora\I18n\Tooling\PoWriter;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/pollora-i18n-'.bin2hex(random_bytes(4));
    mkdir($this->directory);
});

afterEach(function (): void {
    exec('chmod -R u+rwx '.escapeshellarg($this->directory).' && rm -rf '.escapeshellarg($this->directory));
});

function sampleCatalog(string $translation = 'Livre'): Catalog
{
    return new Catalog(['Language' => 'fr'], [new CatalogMessage(new CatalogEntry('Book'), [$translation])]);
}

it('writes a new file, creating its directory', function (): void {
    $path = $this->directory.'/languages/app-fr_FR.po';

    expect((new PoFileWriter)->writeIfChanged(sampleCatalog(), $path))->toBeTrue()
        ->and(file_get_contents($path))->toBe((new PoWriter)->write(sampleCatalog()))
        ->and(fileperms($path) & 0777)->toBe(0666 & ~umask())
        ->and(glob($this->directory.'/languages/*'))->toBe([$path]);
});

it('does not touch a file whose content is already right', function (): void {
    $path = $this->directory.'/app.po';
    $writer = new PoFileWriter;
    $writer->writeIfChanged(sampleCatalog(), $path);
    touch($path, 1_000_000_000);
    clearstatcache();

    expect($writer->writeIfChanged(sampleCatalog(), $path))->toBeFalse();

    clearstatcache();

    expect(filemtime($path))->toBe(1_000_000_000);
});

it('rewrites a file whose content changed, keeping its permissions', function (): void {
    $path = $this->directory.'/app.po';
    $writer = new PoFileWriter;
    $writer->writeIfChanged(sampleCatalog(), $path);
    chmod($path, 0640);

    expect($writer->writeIfChanged(sampleCatalog('Bouquin'), $path))->toBeTrue()
        ->and((new PoReader)->read($path)->get('Book')?->translations)->toBe(['Bouquin'])
        ->and(fileperms($path) & 0777)->toBe(0640)
        ->and(glob($this->directory.'/*'))->toBe([$path]);
});

it('rewrites a file of the same size but another content', function (): void {
    $path = $this->directory.'/app.po';
    file_put_contents($path, str_repeat('x', strlen((new PoWriter)->write(sampleCatalog()))));

    expect((new PoFileWriter)->writeIfChanged(sampleCatalog(), $path))->toBeTrue()
        ->and(file_get_contents($path))->toBe((new PoWriter)->write(sampleCatalog()));
});

it('fails when the directory cannot be created', function (): void {
    file_put_contents($this->directory.'/file', 'x');

    (new PoFileWriter)->writeIfChanged(sampleCatalog(), $this->directory.'/file/sub/app.po');
})->throws(PoFileException::class, 'cannot be created');

it('fails and leaves no temporary file when the directory is read-only', function (): void {
    chmod($this->directory, 0555);

    try {
        (new PoFileWriter)->writeIfChanged(sampleCatalog(), $this->directory.'/app.po');
    } catch (PoFileException $exception) {
        expect($exception->getMessage())->toContain('cannot be written')
            ->and(glob($this->directory.'/*'))->toBe([]);

        return;
    }

    throw new RuntimeException('No exception was raised.');
})->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root ignores directory permissions');

it('fails and leaves no temporary file when the target cannot be replaced', function (): void {
    mkdir($this->directory.'/app.po');

    try {
        (new PoFileWriter)->writeIfChanged(sampleCatalog(), $this->directory.'/app.po');
    } catch (PoFileException $exception) {
        expect($exception->getMessage())->toContain('left untouched')
            ->and(glob($this->directory.'/*'))->toBe([$this->directory.'/app.po']);

        return;
    }

    throw new RuntimeException('No exception was raised.');
});
