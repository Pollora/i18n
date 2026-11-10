<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\FileOutcome;
use Pollora\I18n\Tooling\FileResult;
use Pollora\I18n\Tooling\FileSink;
use Pollora\I18n\Tooling\LanguageFiles;
use Pollora\I18n\Tooling\RunReport;

it('creates, updates and leaves a file alone', function (): void {
    $directory = cliDirectory();
    $sink = new FileSink;

    expect($sink->put($directory.'/sub/a.txt', 'one', false))->toBe(FileOutcome::Created)
        ->and($sink->put($directory.'/sub/a.txt', 'one', false))->toBe(FileOutcome::Unchanged)
        ->and($sink->put($directory.'/sub/a.txt', 'two', false))->toBe(FileOutcome::Updated)
        ->and(file_get_contents($directory.'/sub/a.txt'))->toBe('two');
});

it('says what it would do in a dry run without doing it', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['a.txt' => 'one']);
    $sink = new FileSink;

    expect($sink->put($directory.'/a.txt', 'two', true))->toBe(FileOutcome::Updated)
        ->and($sink->put($directory.'/b.txt', 'two', true))->toBe(FileOutcome::Created)
        ->and($sink->put($directory.'/a.txt', 'one', true))->toBe(FileOutcome::Unchanged)
        ->and(file_get_contents($directory.'/a.txt'))->toBe('one')
        ->and(is_file($directory.'/b.txt'))->toBeFalse();
});

it('counts what a run did', function (): void {
    $report = new RunReport([
        new FileResult('a', FileOutcome::Created),
        new FileResult('b', FileOutcome::Unchanged),
        new FileResult('c', FileOutcome::Updated),
        new FileResult('d', FileOutcome::Unchanged),
    ]);

    expect($report->count(FileOutcome::Unchanged))->toBe(2)
        ->and($report->count(FileOutcome::Created))->toBe(1)
        ->and($report->written())->toBe(2);
});

it('finds the .po files by the names WordPress expects', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['b-fr_FR.po' => '', 'a-de_DE.po' => '', 'a-extra-de_DE.po' => '', 'a-.po' => '', 'a.po' => '', 'a-de_DE.mo' => '']);
    $files = new LanguageFiles;

    expect($files->poFiles($directory, 'a'))->toBe(['de_DE' => $directory.'/a-de_DE.po'])
        ->and(array_keys($files->poFiles($directory)))->toBe(['a-', 'a-de_DE', 'a-extra-de_DE', 'a', 'b-fr_FR'])
        ->and($files->poFiles($directory.'/missing'))->toBe([])
        ->and($files->path($directory.'/', null, 'x', '.mo'))->toBe($directory.'/x.mo');
});
