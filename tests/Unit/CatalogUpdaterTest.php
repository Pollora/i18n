<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\CatalogUpdater;
use Pollora\I18n\Tooling\FileOutcome;
use Pollora\I18n\Tooling\InvalidRunException;
use Pollora\I18n\Tooling\PluralForms;

/**
 * A folder with a template of two messages and, optionally, a French file that translates one of them.
 */
function cliLanguages(bool $french = true): string
{
    $directory = cliDirectory();
    $template = "msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: books\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n\"X-Domain: books\\n\"\n\n#: src/Book.php:7\nmsgid \"Book\"\nmsgstr \"\"\n\n#: src/Book.php:8\nmsgid \"Film\"\nmsgstr \"\"\n";
    cliWrite($directory, ['books.pot' => $template]);

    if ($french) {
        cliWrite($directory, [
            'books-fr_FR.po' => "msgid \"\"\nmsgstr \"\"\n\"Language: fr_FR\\n\"\n\"Plural-Forms: nplurals=2; plural=(n > 1);\\n\"\n\nmsgid \"Book\"\nmsgstr \"Livre\"\n\nmsgid \"Removed\"\nmsgstr \"Supprime\"\n",
            'books-fr_FR.mo' => 'not a po file',
            'other-fr_FR.po' => 'not read',
            'books-extra-fr_FR.po' => 'not read either',
        ]);
    }

    return $directory;
}

it('merges the template into the existing files and keeps their translations', function (): void {
    $directory = cliLanguages();

    $report = (new CatalogUpdater)->update('books', $directory.'/books.pot', $directory);
    $po = (string) file_get_contents($directory.'/books-fr_FR.po');

    expect($report->files)->toHaveCount(1)
        ->and($report->files[0]->outcome)->toBe(FileOutcome::Updated)
        ->and($po)->toContain("msgid \"Book\"\nmsgstr \"Livre\"")
        ->and($po)->toContain("msgid \"Film\"\nmsgstr \"\"")
        ->and($po)->toContain("#~ msgid \"Removed\"\n#~ msgstr \"Supprime\"")
        ->and($po)->toContain('"Plural-Forms: nplurals=2; plural=(n > 1);\n"')
        ->and(file_get_contents($directory.'/other-fr_FR.po'))->toBe('not read');
});

it('creates the files of the locales asked for, with their language and plural forms', function (): void {
    $directory = cliLanguages(false);

    $report = (new CatalogUpdater)->update('books', $directory.'/books.pot', $directory, ['ru_RU', 'fr_FR', 'xx']);
    $french = (string) file_get_contents($directory.'/books-fr_FR.po');

    expect(array_map(static fn ($file): string => basename($file->path), $report->files))->toBe(['books-fr_FR.po', 'books-ru_RU.po', 'books-xx.po'])
        ->and($report->count(FileOutcome::Created))->toBe(3)
        ->and($french)->toContain('"Language: fr_FR\n"')
        ->and($french)->toContain('"Plural-Forms: nplurals=2; plural=(n > 1);\n"')
        ->and($french)->not->toContain('X-Domain')
        ->and((string) file_get_contents($directory.'/books-ru_RU.po'))->toContain('nplurals=3;')
        ->and((string) file_get_contents($directory.'/books-xx.po'))->not->toContain('Plural-Forms');
});

it('creates the folder of the first file', function (): void {
    $directory = cliLanguages(false);

    (new CatalogUpdater)->update('books', $directory.'/books.pot', $directory.'/new/sub', ['fr_FR']);

    expect(is_file($directory.'/new/sub/books-fr_FR.po'))->toBeTrue();
});

it('touches nothing the second time', function (): void {
    $directory = cliLanguages();
    $updater = new CatalogUpdater;
    $updater->update('books', $directory.'/books.pot', $directory, ['de_DE']);
    $before = cliSnapshot($directory);

    $report = $updater->update('books', $directory.'/books.pot', $directory, ['de_DE']);

    expect($report->written())->toBe(0)
        ->and($report->count(FileOutcome::Unchanged))->toBe(2)
        ->and(cliSnapshot($directory))->toBe($before);
});

it('reports the same figures in a dry run and writes nothing', function (): void {
    $directory = cliLanguages();
    $before = cliSnapshot($directory);

    $report = (new CatalogUpdater)->update('books', $directory.'/books.pot', $directory, ['de_DE'], dryRun: true);

    expect($report->count(FileOutcome::Created))->toBe(1)
        ->and($report->count(FileOutcome::Updated))->toBe(1)
        ->and(cliSnapshot($directory))->toBe($before);
});

it('does nothing when no file matches', function (): void {
    $directory = cliLanguages(false);

    expect((new CatalogUpdater)->update('books', $directory.'/books.pot', $directory)->files)->toBe([]);
});

it('refuses a locale that is not a locale', function (): void {
    $directory = cliLanguages();

    (new CatalogUpdater)->update('books', $directory.'/books.pot', $directory, ['fr-FR']);
})->throws(InvalidRunException::class, 'The locale "fr-FR" is not a WordPress locale');

it('refuses a template that does not exist', function (): void {
    (new CatalogUpdater)->update('books', '/nowhere/books.pot', cliDirectory());
})->throws(InvalidRunException::class, 'The template "/nowhere/books.pot" does not exist.');

it('refuses a folder that does not exist when it has no locale to create', function (): void {
    $directory = cliLanguages();

    (new CatalogUpdater)->update('books', $directory.'/books.pot', $directory.'/missing');
})->throws(InvalidRunException::class, 'does not exist');

it('knows the plural forms of the common languages', function (string $locale, ?string $forms): void {
    expect(PluralForms::forLocale($locale))->toBe($forms);
})->with([
    'English' => ['en_US', PluralForms::ENGLISH],
    'French' => ['fr_FR', 'nplurals=2; plural=(n > 1);'],
    'Brazilian Portuguese' => ['pt_BR', 'nplurals=2; plural=(n > 1);'],
    'Portuguese' => ['pt_PT', PluralForms::ENGLISH],
    'Japanese' => ['ja', 'nplurals=1; plural=0;'],
    'Unknown' => ['xx_YY', null],
]);
