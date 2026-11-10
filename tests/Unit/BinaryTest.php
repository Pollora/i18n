<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Console\ExitCode;

/**
 * Runs extract, update (after the translations were filled in) and compile in a project folder.
 *
 * @return array{project: string, extract: array{code: int, out: string, err: string}}
 */
function cliPipeline(): array
{
    $project = cliProject();
    $extract = cliRun(['extract', '--domain=books', '--path=src', '--declarations=declarations.php', '--cache=build/cache.php'], $project);

    return ['project' => $project, 'extract' => $extract];
}

/**
 * Runs `xgettext`-free gettext checks when msgunfmt is installed; otherwise null.
 *
 * @return array<string, string>|null
 */
function cliMsgunfmt(string $mo): ?array
{
    if (trim((string) shell_exec('command -v msgunfmt 2>/dev/null')) === '') {
        return null;
    }

    $text = (string) shell_exec('msgunfmt '.escapeshellarg($mo).' 2>&1');
    preg_match_all('/msgid "(.+)"\nmsgstr "(.+)"/', $text, $found);

    return array_combine($found[1], $found[2]);
}

it('is declared as a binary of the package', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['bin'])->toBe(['bin/pollora-i18n'])
        ->and($composer['require'])->not->toHaveKey('symfony/console')
        ->and($composer['require-dev'])->toHaveKey('symfony/console')
        ->and($composer['suggest'])->toHaveKey('symfony/console')
        ->and(is_executable(dirname(__DIR__, 2).'/bin/pollora-i18n'))->toBeTrue();
});

it('goes from the sources to the compiled files and stops writing the second time', function (): void {
    ['project' => $project, 'extract' => $extract] = cliPipeline();

    expect($extract['code'])->toBe(ExitCode::SUCCESS)
        ->and($extract['out'])->toBe("created   languages/books.pot\n1 created, 0 updated, 0 unchanged\n");

    $update = cliRun(['update', '--domain=books', '--locale=fr_FR', '--locale=de_DE'], $project);

    expect($update['code'])->toBe(ExitCode::SUCCESS)
        ->and($update['out'])->toBe("created   languages/books-de_DE.po\ncreated   languages/books-fr_FR.po\n2 created, 0 updated, 0 unchanged\n");

    $french = (string) file_get_contents($project.'/languages/books-fr_FR.po');
    file_put_contents($project.'/languages/books-fr_FR.po', str_replace(["msgid \"Book\"\nmsgstr \"\"", "msgid \"Hello\"\nmsgstr \"\""], ["msgid \"Book\"\nmsgstr \"Livre\"", "msgid \"Hello\"\nmsgstr \"Bonjour\""], $french));

    $compile = cliRun(['compile', '--domain=books'], $project);

    expect($compile['code'])->toBe(ExitCode::SUCCESS)
        ->and($compile['out'])->toBe("created   languages/books-de_DE.mo\ncreated   languages/books-de_DE.l10n.php\ncreated   languages/books-fr_FR.mo\ncreated   languages/books-fr_FR.l10n.php\n4 created, 0 updated, 0 unchanged\n")
        ->and(cliReadMo($project.'/languages/books-fr_FR.mo')['Book'])->toBe('Livre');

    $again = [
        cliRun(['extract', '--domain=books', '--path=src', '--declarations=declarations.php', '--cache=build/cache.php'], $project),
        cliRun(['update', '--domain=books', '--locale=fr_FR', '--locale=de_DE'], $project),
        cliRun(['compile', '--domain=books'], $project),
    ];
    $snapshot = cliSnapshot($project);
    sleep(1);
    $third = [
        cliRun(['extract', '--domain=books', '--path=src', '--declarations=declarations.php', '--cache=build/cache.php'], $project),
        cliRun(['update', '--domain=books', '--locale=fr_FR', '--locale=de_DE'], $project),
        cliRun(['compile', '--domain=books', '--force'], $project),
    ];

    expect(array_map(static fn (array $run): string => $run['out'], $again))->toBe([
        "unchanged languages/books.pot\n0 created, 0 updated, 1 unchanged\n",
        "unchanged languages/books-de_DE.po\nunchanged languages/books-fr_FR.po\n0 created, 0 updated, 2 unchanged\n",
        "unchanged languages/books-de_DE.mo\nunchanged languages/books-de_DE.l10n.php\nunchanged languages/books-fr_FR.mo\nunchanged languages/books-fr_FR.l10n.php\n0 created, 0 updated, 4 unchanged\n",
    ])
        ->and(array_column($third, 'code'))->toBe([0, 0, 0])
        ->and(cliSnapshot($project))->toBe($snapshot);
});

it('leaves the fuzzy and untranslated messages out of the compiled files', function (): void {
    ['project' => $project] = cliPipeline();
    cliRun(['update', '--domain=books', '--locale=fr_FR'], $project);
    $french = (string) file_get_contents($project.'/languages/books-fr_FR.po');
    file_put_contents($project.'/languages/books-fr_FR.po', str_replace(["msgid \"Book\"\nmsgstr \"\"", "msgid \"Hello\"\nmsgstr \"\""], ["msgid \"Book\"\nmsgstr \"Livre\"", "#, fuzzy\nmsgid \"Hello\"\nmsgstr \"Bonjour\""], $french));

    cliRun(['compile', '--domain=books'], $project);
    $php = require $project.'/languages/books-fr_FR.l10n.php';
    $mo = cliReadMo($project.'/languages/books-fr_FR.mo');

    expect($php['messages'])->toBe(['Book' => 'Livre'])
        ->and(array_diff_key($mo, ['' => 1]))->toBe(['Book' => 'Livre']);

    if (($unfmt = cliMsgunfmt($project.'/languages/books-fr_FR.mo')) !== null) {
        expect($unfmt)->toBe(['Book' => 'Livre']);
    }
});

it('writes nothing in a dry run and counts what a real run would do', function (): void {
    $project = cliProject();
    $arguments = ['extract', '--domain=books', '--path=src', '--declarations=declarations.php', '--cache=build/cache.php'];
    $before = cliSnapshot($project);

    $dry = cliRun([...$arguments, '--dry-run'], $project);

    expect($dry['out'])->toBe("created   languages/books.pot\n1 created, 0 updated, 0 unchanged (dry run: nothing was written)\n")
        ->and(cliSnapshot($project))->toBe($before);

    $real = cliRun($arguments, $project);

    expect($real['out'])->toBe("created   languages/books.pot\n1 created, 0 updated, 0 unchanged\n");
});

it('unions the template of wp i18n make-pot', function (): void {
    $project = cliProject(['external.pot' => "msgid \"\"\nmsgstr \"\"\n\n#: assets/app.js:3\nmsgid \"From JavaScript\"\nmsgstr \"\"\n"]);

    $run = cliRun(['extract', '--domain=books', '--path=src', '--declarations=declarations.php', '--merge=external.pot'], $project);
    $pot = (string) file_get_contents($project.'/languages/books.pot');

    expect($run['code'])->toBe(ExitCode::SUCCESS)
        ->and($pot)->toContain('msgid "From JavaScript"')
        ->and($pot)->toContain('msgid "Book"');
});

it('fails with --strict when a string cannot be extracted, after writing the template', function (): void {
    $project = cliProject(['src/Dynamic.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label(SOME_CONSTANT)]\nclass Dynamic {}\n"]);
    $arguments = ['extract', '--domain=books', '--path=src', '--declarations=declarations.php'];

    $lenient = cliRun($arguments, $project);
    $strict = cliRun([...$arguments, '--strict'], $project);

    expect($lenient['code'])->toBe(ExitCode::SUCCESS)
        ->and($lenient['err'])->toStartWith('warning: src/Dynamic.php:3: ')
        ->and($strict['code'])->toBe(ExitCode::DIAGNOSTICS)
        ->and($strict['out'])->toBe("unchanged languages/books.pot\n0 created, 0 updated, 1 unchanged\n");
});

it('stays silent with --quiet but keeps its exit code', function (): void {
    $project = cliProject();

    $run = cliRun(['extract', '--domain=books', '--path=missing', '--declarations=declarations.php', '--quiet'], $project);

    expect($run['code'])->toBe(ExitCode::FAILURE)
        ->and($run['out'].$run['err'])->toBe('');
});

it('exits with the usage code and says which option is missing', function (): void {
    $run = cliRun(['compile', '--languages=nowhere'], cliDirectory());

    expect($run['code'])->toBe(ExitCode::USAGE)
        ->and($run['err'])->toBe("error: The directory \"nowhere\" does not exist.\n");
});

it('says how to install symfony/console when it is missing', function (): void {
    $root = cliDirectory();
    cliWrite($root, ['vendor/autoload.php' => "<?php\n", 'vendor/pollora/i18n/bin/pollora-i18n' => (string) file_get_contents(dirname(__DIR__, 2).'/bin/pollora-i18n')]);

    $run = cliRun(['extract'], $root, $root.'/vendor/pollora/i18n/bin/pollora-i18n');

    expect($run['code'])->toBe(1)
        ->and($run['err'])->toContain('symfony/console is not installed')
        ->and($run['err'])->toContain('composer require --dev symfony/console');
});

it('says so when there is no autoloader', function (): void {
    $root = cliDirectory();
    cliWrite($root, ['bin/pollora-i18n' => (string) file_get_contents(dirname(__DIR__, 2).'/bin/pollora-i18n')]);

    $run = cliRun([], $root, $root.'/bin/pollora-i18n');

    expect($run['code'])->toBe(1)
        ->and($run['err'])->toContain('autoloader was not found');
});

it('compiles files that WordPress reads', function (): void {
    $wordpress = null;

    foreach ([getenv('POLLORA_WORDPRESS_PATH'), '/var/www/html/public/cms'] as $path) {
        if (is_string($path) && is_file($path.'/wp-includes/pomo/mo.php')) {
            $wordpress = $path;
        }
    }

    if ($wordpress === null) {
        $this->markTestSkipped('WordPress is not installed here (set POLLORA_WORDPRESS_PATH).');
    }

    foreach (['pomo/entry', 'pomo/translations', 'pomo/streams', 'pomo/plural-forms', 'pomo/mo', 'l10n/class-wp-translation-file', 'l10n/class-wp-translation-file-mo', 'l10n/class-wp-translation-file-php'] as $file) {
        require_once $wordpress.'/wp-includes/'.$file.'.php';
    }

    ['project' => $project] = cliPipeline();
    cliRun(['update', '--domain=books', '--locale=fr_FR'], $project);
    file_put_contents($project.'/languages/books-fr_FR.po', str_replace("msgid \"Book\"\nmsgstr \"\"", "msgid \"Book\"\nmsgstr \"Livre\"", (string) file_get_contents($project.'/languages/books-fr_FR.po')));
    cliRun(['compile', '--domain=books'], $project);

    $mo = new MO;
    $fromMo = WP_Translation_File::create($project.'/languages/books-fr_FR.mo');
    $fromPhp = WP_Translation_File::create($project.'/languages/books-fr_FR.l10n.php', 'php');

    expect($mo->import_from_file($project.'/languages/books-fr_FR.mo'))->toBeTrue()
        ->and($mo->translate('Book'))->toBe('Livre')
        ->and($mo->get_header('Language'))->toBe('fr_FR')
        ->and($fromMo->error())->toBeNull()
        ->and($fromPhp->error())->toBeNull()
        ->and($fromPhp->entries())->toEqual($fromMo->entries())
        ->and($fromPhp->translate('Book'))->toBe('Livre');
});
