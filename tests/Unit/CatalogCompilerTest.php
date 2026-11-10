<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\CatalogCompiler;
use Pollora\I18n\Tooling\CompiledFormat;
use Pollora\I18n\Tooling\FileOutcome;
use Pollora\I18n\Tooling\InvalidRunException;
use Pollora\I18n\Tooling\PoParseException;

function cliLanguagesWithPo(): string
{
    $directory = cliDirectory();
    cliWrite($directory, [
        'books-fr_FR.po' => cliFrenchPo(),
        'other-fr_FR.po' => cliFrenchPo(),
        'notes.txt' => 'ignored',
    ]);

    return $directory;
}

it('compiles the translated messages to .mo and .l10n.php files', function (): void {
    $directory = cliLanguagesWithPo();

    $report = (new CatalogCompiler)->compile($directory, 'books');
    $php = require $directory.'/books-fr_FR.l10n.php';

    expect(array_map(static fn ($file): string => basename($file->path), $report->files))->toBe(['books-fr_FR.mo', 'books-fr_FR.l10n.php'])
        ->and($report->count(FileOutcome::Created))->toBe(2)
        ->and(cliReadMo($directory.'/books-fr_FR.mo'))->toBe([
            '' => "Content-Type: text/plain; charset=UTF-8\nLanguage: fr_FR\nPlural-Forms: nplurals=2; plural=(n > 1);\n",
            'Book' => 'Livre',
            "menu\x04File\0Files" => "Fichier\0Fichiers",
        ])
        ->and($php['messages'])->toBe(['Book' => 'Livre', "menu\x04File" => "Fichier\0Fichiers"])
        ->and($php['language'])->toBe('fr_FR')
        ->and(is_file($directory.'/other-fr_FR.mo'))->toBeFalse();
});

it('compiles every .po file when no domain is given', function (): void {
    $directory = cliLanguagesWithPo();

    $report = (new CatalogCompiler)->compile($directory, formats: [CompiledFormat::Mo]);

    expect(array_map(static fn ($file): string => basename($file->path), $report->files))->toBe(['books-fr_FR.mo', 'other-fr_FR.mo']);
});

it('skips a compiled file that is newer than its .po file without reading the .po file', function (): void {
    $directory = cliLanguagesWithPo();
    $compiler = new CatalogCompiler;
    $compiler->compile($directory, 'books');
    file_put_contents($directory.'/books-fr_FR.po', 'broken, and not read');
    cliAge($directory.'/books-fr_FR.po', time() - 100);

    $report = $compiler->compile($directory, 'books');

    expect($report->count(FileOutcome::Unchanged))->toBe(2);
});

it('recompiles when the .po file is newer and rewrites only what changed', function (): void {
    $directory = cliLanguagesWithPo();
    $compiler = new CatalogCompiler;
    $compiler->compile($directory, 'books');
    cliAge($directory.'/books-fr_FR.mo', time() - 100);
    cliAge($directory.'/books-fr_FR.l10n.php', time() - 100);
    cliAge($directory.'/books-fr_FR.po', time() - 50);
    $before = cliSnapshot($directory);

    $same = $compiler->compile($directory, 'books');

    expect($same->written())->toBe(0)
        ->and(cliSnapshot($directory))->toBe($before);

    file_put_contents($directory.'/books-fr_FR.po', str_replace('Livre', 'Bouquin', cliFrenchPo()));
    $changed = $compiler->compile($directory, 'books');

    expect($changed->count(FileOutcome::Updated))->toBe(2)
        ->and(cliReadMo($directory.'/books-fr_FR.mo')['Book'])->toBe('Bouquin');
});

it('builds files that are newer when it is forced to', function (): void {
    $directory = cliLanguagesWithPo();
    $compiler = new CatalogCompiler;
    $compiler->compile($directory, 'books', [CompiledFormat::Mo]);
    file_put_contents($directory.'/books-fr_FR.mo', 'damaged');
    cliAge($directory.'/books-fr_FR.po', time() - 100);

    expect($compiler->compile($directory, 'books', [CompiledFormat::Mo])->written())->toBe(0)
        ->and($compiler->compile($directory, 'books', [CompiledFormat::Mo], force: true)->files[0]->outcome)->toBe(FileOutcome::Updated)
        ->and(cliReadMo($directory.'/books-fr_FR.mo')['Book'])->toBe('Livre');
});

it('writes nothing in a dry run and counts the same', function (): void {
    $directory = cliLanguagesWithPo();
    $before = cliSnapshot($directory);

    $report = (new CatalogCompiler)->compile($directory, dryRun: true);

    expect($report->count(FileOutcome::Created))->toBe(4)
        ->and(cliSnapshot($directory))->toBe($before);
});

it('stops on a .po file it cannot read', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['books-fr_FR.po' => "msgid \"x\"\nmsgstr \"y\"\nmsgbad"]);

    (new CatalogCompiler)->compile($directory);
})->throws(PoParseException::class);

it('refuses a folder that does not exist', function (): void {
    (new CatalogCompiler)->compile('/nowhere');
})->throws(InvalidRunException::class, 'The directory "/nowhere" does not exist.');

it('reads a list of formats', function (): void {
    expect(CompiledFormat::fromList('php,mo,php'))->toBe([CompiledFormat::Mo, CompiledFormat::Php])
        ->and(CompiledFormat::fromList(' mo '))->toBe([CompiledFormat::Mo]);

    CompiledFormat::fromList('mo,json');
})->throws(InvalidRunException::class, 'The format "json" is unknown');
