<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tooling\AttributeSourceException;
use Pollora\I18n\Tooling\CatalogExtractor;
use Pollora\I18n\Tooling\FileOutcome;
use Pollora\I18n\Tooling\InvalidDeclarationException;
use Pollora\I18n\Tooling\InvalidRunException;
use Pollora\I18n\Tooling\RunReport;

/**
 * @param  array<string, mixed>  $arguments
 */
function cliExtract(string $project, array $arguments = []): RunReport
{
    return (new CatalogExtractor)->extract(...[
        'domain' => 'books',
        'paths' => [$project.'/src'],
        'output' => $project.'/languages/books.pot',
        'basePath' => $project,
        'declarations' => [$project.'/declarations.php'],
        ...$arguments,
    ]);
}

beforeEach(fn () => TranslatableRegistry::flush());

it('writes the strings of the domain to a template with references relative to the base path', function (): void {
    $project = cliProject();

    $report = cliExtract($project);
    $pot = (string) file_get_contents($project.'/languages/books.pot');

    expect($report->files[0]->outcome)->toBe(FileOutcome::Created)
        ->and($report->files[0]->path)->toBe($project.'/languages/books.pot')
        ->and($pot)->toContain('"Project-Id-Version: books\n"')
        ->and($pot)->toContain('"Plural-Forms: nplurals=2; plural=(n != 1);\n"')
        ->and($pot)->toContain("#. Attribute PostType::singular\n#: src/Book.php:")
        ->and($pot)->toContain('msgid "Book"')
        ->and($pot)->toContain('msgid "Books"')
        ->and($pot)->toContain('msgid "Hello"')
        ->and($pot)->not->toContain('Film')
        ->and($pot)->not->toContain('POT-Creation-Date');
});

it('leaves the file alone the second time', function (): void {
    $project = cliProject();
    cliExtract($project);
    cliAge($project.'/languages/books.pot', 1_000_000_000);

    $report = cliExtract($project);

    expect($report->files[0]->outcome)->toBe(FileOutcome::Unchanged)
        ->and(filemtime($project.'/languages/books.pot'))->toBe(1_000_000_000);
});

it('reports an update when the sources changed', function (): void {
    $project = cliProject();
    cliExtract($project);
    cliWrite($project, ['src/More.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('More')]\nclass More {}\n"]);

    expect(cliExtract($project)->files[0]->outcome)->toBe(FileOutcome::Updated)
        ->and((string) file_get_contents($project.'/languages/books.pot'))->toContain('msgid "More"');
});

it('writes nothing in a dry run and still says what would happen', function (): void {
    $project = cliProject();

    $report = cliExtract($project, ['dryRun' => true, 'cache' => $project.'/cache.php']);

    expect($report->files[0]->outcome)->toBe(FileOutcome::Created)
        ->and(is_file($project.'/languages/books.pot'))->toBeFalse()
        ->and(is_file($project.'/cache.php'))->toBeFalse();
});

it('parses only the files that changed when it has a cache', function (): void {
    $project = cliProject();

    $first = cliExtract($project, ['cache' => $project.'/cache.php']);
    $second = cliExtract($project, ['cache' => $project.'/cache.php']);

    expect($first->notes[0])->toStartWith('1 of 1 ')
        ->and($second->notes[0])->toStartWith('0 of 1 ')
        ->and($second->files[0]->outcome)->toBe(FileOutcome::Unchanged);
});

it('unions an external template and keeps its own headers', function (): void {
    $project = cliProject([
        'make-pot.pot' => "msgid \"\"\nmsgstr \"\"\n\"POT-Creation-Date: 2026-01-01 00:00+0000\\n\"\n\n#: assets/app.js:3\nmsgid \"From JavaScript\"\nmsgstr \"\"\n\n#: src/Book.php:9\nmsgid \"Book\"\nmsgstr \"\"\n",
    ]);

    cliExtract($project, ['merge' => [$project.'/make-pot.pot']]);
    $pot = (string) file_get_contents($project.'/languages/books.pot');

    expect($pot)->toContain('msgid "From JavaScript"')
        ->and($pot)->toContain('#: assets/app.js:3')
        ->and(substr_count($pot, 'msgid "Book"'))->toBe(1)
        ->and($pot)->not->toContain('POT-Creation-Date');
});

it('refuses a template to merge that does not exist', function (): void {
    cliExtract(cliProject(), ['merge' => ['/nowhere/make-pot.pot']]);
})->throws(InvalidRunException::class, 'The template "/nowhere/make-pot.pot" does not exist.');

it('passes on the diagnostics of the extraction', function (): void {
    $project = cliProject(['src/Dynamic.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label(SOME_CONSTANT)]\nclass Dynamic {}\n"]);

    $report = cliExtract($project);

    expect($report->diagnostics)->toHaveCount(1)
        ->and($report->diagnostics[0])->toStartWith('src/Dynamic.php:3: ');
});

it('declares the attributes the installed packages announce', function (): void {
    $project = cliProject([
        'vendor/composer/installed.json' => json_encode(['packages' => [[
            'name' => 'acme/books',
            'install-path' => '../acme/books',
            'extra' => ['pollora' => ['i18n' => 'resources/i18n.php']],
        ]]], JSON_THROW_ON_ERROR),
        'vendor/acme/books/resources/i18n.php' => "<?php\n\nreturn ['Pollora\\\\I18n\\\\Tests\\\\Fixtures\\\\Scan\\\\Label' => ['value']];\n",
    ]);

    $found = cliExtract($project, ['declarations' => []]);
    $pot = (string) file_get_contents($project.'/languages/books.pot');

    expect($found->files[0]->outcome)->toBe(FileOutcome::Created)
        ->and($pot)->toContain('msgid "Hello"')
        ->and($pot)->not->toContain('msgid "Book"');
});

it('extracts nothing without declarations and still writes the header', function (): void {
    $project = cliProject();

    $report = cliExtract($project, ['declarations' => []]);

    expect($report->diagnostics)->toHaveCount(1)
        ->and($report->diagnostics[0])->toStartWith('No translatable attribute is declared')
        ->and((string) file_get_contents($project.'/languages/books.pot'))->not->toContain('msgid "Book"')
        ->and((string) file_get_contents($project.'/languages/books.pot'))->toContain('msgid ""');
});

it('extracts a Translatable without any declaration, and does not claim that nothing is declared', function (): void {
    $project = cliProject(['src/Columns.php' => "<?php\nuse Pollora\\I18n\\Runtime\\Translatable as T;\n#[Columns(['a' => new T('Written by', 'books')])]\nclass Columns {}\n"]);

    $report = cliExtract($project, ['declarations' => []]);

    expect($report->diagnostics)->toBe([])
        ->and((string) file_get_contents($project.'/languages/books.pot'))->toContain('msgid "Written by"')->toContain('#. Attribute Columns › a');
});

it('fails on an installed.json that is not there when it was asked for', function (): void {
    cliExtract(cliProject(), ['installed' => '/nowhere/installed.json']);
})->throws(InvalidDeclarationException::class);

it('fails on a path that does not exist', function (): void {
    $project = cliProject();

    cliExtract($project, ['paths' => [$project.'/missing']]);
})->throws(AttributeSourceException::class);
