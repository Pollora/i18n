<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\AtomicFileWriter;
use Pollora\I18n\Tooling\FileWriteException;

beforeEach(function (): void {
    // The failures under test come with PHP warnings; the writer turns them into exceptions.
    set_error_handler(static fn (): bool => true);
    $this->directory = sys_get_temp_dir().'/pollora-i18n-writer-'.bin2hex(random_bytes(4));
    mkdir($this->directory);
});

afterEach(function (): void {
    restore_error_handler();
    chmod($this->directory, 0777);
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($this->directory);
});

it('writes the file and creates the missing directories', function (): void {
    $path = $this->directory.'/languages/fr/app.mo';

    expect((new AtomicFileWriter)->writeIfChanged($path, 'bytes'))->toBeTrue()
        ->and(file_get_contents($path))->toBe('bytes');
});

it('gives the file the permissions of a regular file and leaves no temporary file behind', function (): void {
    $path = $this->directory.'/app.mo';

    (new AtomicFileWriter)->writeIfChanged($path, 'bytes');

    expect(fileperms($path) & 0777)->toBe(0666 & ~umask())
        ->and(scandir($this->directory))->toBe(['.', '..', 'app.mo']);
});

it('does not touch a file that already holds the contents', function (): void {
    $path = $this->directory.'/app.mo';
    $writer = new AtomicFileWriter;
    $writer->writeIfChanged($path, 'bytes');
    touch($path, 1_000_000_000);
    clearstatcache();

    expect($writer->writeIfChanged($path, 'bytes'))->toBeFalse();

    clearstatcache();

    expect(filemtime($path))->toBe(1_000_000_000);
});

it('replaces a file whose contents differ, even by one byte or in length only', function (): void {
    $path = $this->directory.'/app.mo';
    $writer = new AtomicFileWriter;
    $writer->writeIfChanged($path, 'bytes');

    expect($writer->writeIfChanged($path, 'byteS'))->toBeTrue()
        ->and(file_get_contents($path))->toBe('byteS')
        ->and($writer->writeIfChanged($path, 'bytes and more'))->toBeTrue()
        ->and(file_get_contents($path))->toBe('bytes and more');
});

it('fails with a named exception when the directory cannot be created', function (): void {
    file_put_contents($this->directory.'/blocker', 'a file');

    (new AtomicFileWriter)->writeIfChanged($this->directory.'/blocker/app.mo', 'bytes');
})->throws(FileWriteException::class, 'could not be created');

it('fails with a named exception and cleans up when the target cannot be replaced', function (): void {
    mkdir($this->directory.'/app.mo');

    try {
        (new AtomicFileWriter)->writeIfChanged($this->directory.'/app.mo', 'bytes');
        $this->fail('The write should have failed.');
    } catch (FileWriteException $exception) {
        expect($exception->getMessage())->toContain('could not be written')
            ->and(scandir($this->directory))->toBe(['.', '..', 'app.mo']);
    }
});

it('fails with a named exception when the temporary file cannot be created', function (): void {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $this->markTestSkipped('Root ignores directory permissions.');
    }

    chmod($this->directory, 0555);

    (new AtomicFileWriter)->writeIfChanged($this->directory.'/app.mo', 'bytes');
})->throws(FileWriteException::class, 'could not be written');
