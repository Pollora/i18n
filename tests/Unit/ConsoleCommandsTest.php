<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tooling\Console\Cli;
use Pollora\I18n\Tooling\Console\CompileCommand;
use Pollora\I18n\Tooling\Console\ExitCode;
use Pollora\I18n\Tooling\Console\ExtractCommand;
use Pollora\I18n\Tooling\Console\UpdateCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

function cliTester(string $project): ApplicationTester
{
    $application = new Application('pollora-i18n');
    $application->setAutoExit(false);
    $application->addCommands([new ExtractCommand(basePath: $project), new UpdateCommand, new CompileCommand]);

    return new ApplicationTester($application);
}

/**
 * @param  array<string, mixed>  $input
 * @param  array<string, mixed>  $options
 */
function cliExecute(ApplicationTester $tester, array $input, array $options = []): int
{
    return $tester->run($input, ['capture_stderr_separately' => true, ...$options]);
}

beforeEach(fn () => TranslatableRegistry::flush());

it('extracts, one line per file and a summary', function (): void {
    $project = cliProject();
    $tester = cliTester($project);

    $code = cliExecute($tester, ['command' => 'extract', '--domain' => 'books', '--path' => [$project.'/src'], '--declarations' => [$project.'/declarations.php'], '--output' => $project.'/out/books.pot']);

    expect($code)->toBe(ExitCode::SUCCESS)
        ->and($tester->getDisplay())->toBe("created   {$project}/out/books.pot\n1 created, 0 updated, 0 unchanged\n")
        ->and($tester->getErrorOutput())->toBe('');

    cliExecute($tester, ['command' => 'extract', '--domain' => 'books', '--path' => [$project.'/src'], '--declarations' => [$project.'/declarations.php'], '--output' => $project.'/out/books.pot']);

    expect($tester->getDisplay())->toBe("unchanged {$project}/out/books.pot\n0 created, 0 updated, 1 unchanged\n");
});

it('puts the pot in languages/{domain}.pot by default', function (): void {
    $project = cliProject();
    $previous = getcwd();
    chdir($project);

    try {
        $code = cliExecute(cliTester($project), ['command' => 'extract', '--domain' => 'books', '--path' => ['src'], '--declarations' => ['declarations.php']]);
    } finally {
        chdir((string) $previous);
    }

    expect($code)->toBe(ExitCode::SUCCESS)
        ->and(is_file($project.'/languages/books.pot'))->toBeTrue();
});

it('says it is a dry run and writes nothing', function (): void {
    $project = cliProject();
    $tester = cliTester($project);

    $code = cliExecute($tester, ['command' => 'extract', '--domain' => 'books', '--path' => [$project.'/src'], '--declarations' => [$project.'/declarations.php'], '--output' => $project.'/out/books.pot', '--dry-run' => true]);

    expect($code)->toBe(ExitCode::SUCCESS)
        ->and($tester->getDisplay())->toEndWith("1 created, 0 updated, 0 unchanged (dry run: nothing was written)\n")
        ->and(is_dir($project.'/out'))->toBeFalse();
});

it('prints nothing with --quiet and the figures with -v', function (): void {
    $project = cliProject();
    $tester = cliTester($project);
    $arguments = ['command' => 'extract', '--domain' => 'books', '--path' => [$project.'/src'], '--declarations' => [$project.'/declarations.php'], '--output' => $project.'/out/books.pot'];

    $quiet = cliExecute($tester, [...$arguments, '--quiet' => true]);

    expect($quiet)->toBe(ExitCode::SUCCESS)
        ->and($tester->getDisplay())->toBe('')
        ->and(is_file($project.'/out/books.pot'))->toBeTrue();

    cliExecute($tester, $arguments, ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

    expect($tester->getDisplay())->toContain('files with a translatable attribute parsed');
});

it('warns about strings it cannot extract and fails only with --strict', function (): void {
    $project = cliProject(['src/Dynamic.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label(SOME_CONSTANT)]\nclass Dynamic {}\n"]);
    $tester = cliTester($project);
    $arguments = ['command' => 'extract', '--domain' => 'books', '--path' => [$project.'/src'], '--declarations' => [$project.'/declarations.php'], '--output' => $project.'/out/books.pot'];

    $lenient = cliExecute($tester, $arguments);

    expect($lenient)->toBe(ExitCode::SUCCESS)
        ->and($tester->getErrorOutput())->toContain('warning: src/Dynamic.php:3: ');

    $strict = cliExecute($tester, [...$arguments, '--strict' => true]);

    expect($strict)->toBe(ExitCode::DIAGNOSTICS);
});

it('exits with the usage code when an option is missing', function (array $input, string $message): void {
    $tester = cliTester(cliDirectory());

    $code = cliExecute($tester, $input);

    expect($code)->toBe(ExitCode::USAGE)
        ->and($tester->getErrorOutput())->toBe("error: {$message}\n")
        ->and($tester->getDisplay())->toBe('');
})->with([
    'extract without a domain' => [['command' => 'extract', '--path' => ['src']], 'The option "--domain" is required.'],
    'extract without a path' => [['command' => 'extract', '--domain' => 'books'], 'The option "--path" is required.'],
    'update without a domain' => [['command' => 'update'], 'The option "--domain" is required.'],
    'compile with an unknown format' => [['command' => 'compile', '--format' => 'json'], 'The format "json" is unknown; use "mo", "php" or both separated by a comma.'],
]);

it('exits with the failure code when a file cannot be used', function (): void {
    $directory = cliProject();
    $tester = cliTester($directory);

    $code = cliExecute($tester, ['command' => 'extract', '--domain' => 'books', '--path' => [$directory.'/missing'], '--declarations' => [$directory.'/declarations.php']]);

    expect($code)->toBe(ExitCode::FAILURE)
        ->and($tester->getErrorOutput())->toBe("error: The path \"{$directory}/missing\" is neither a file nor a directory.\n");
});

it('updates and compiles the translations', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['languages/books.pot' => "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Book\"\nmsgstr \"\"\n"]);
    $tester = cliTester($directory);

    $update = cliExecute($tester, ['command' => 'update', '--domain' => 'books', '--languages' => $directory.'/languages', '--locale' => ['fr_FR']]);

    expect($update)->toBe(ExitCode::SUCCESS)
        ->and($tester->getDisplay())->toBe("created   {$directory}/languages/books-fr_FR.po\n1 created, 0 updated, 0 unchanged\n");

    file_put_contents($directory.'/languages/books-fr_FR.po', str_replace("msgid \"Book\"\nmsgstr \"\"", "msgid \"Book\"\nmsgstr \"Livre\"", (string) file_get_contents($directory.'/languages/books-fr_FR.po')));

    $compile = cliExecute($tester, ['command' => 'compile', '--languages' => $directory.'/languages', '--domain' => 'books', '--format' => 'mo']);

    expect($compile)->toBe(ExitCode::SUCCESS)
        ->and($tester->getDisplay())->toBe("created   {$directory}/languages/books-fr_FR.mo\n1 created, 0 updated, 0 unchanged\n")
        ->and(cliReadMo($directory.'/languages/books-fr_FR.mo')['Book'])->toBe('Livre');
});

it('lists its three commands', function (): void {
    expect(Cli::run(new StringInput('list --quiet')))->toBe(ExitCode::SUCCESS)
        ->and(array_keys(Cli::application()->all()))->toContain('extract', 'update', 'compile');
});
