<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatableRegistry;
use Pollora\I18n\Tooling\AtomicFileWriter;
use Pollora\I18n\Tooling\Catalog;
use Pollora\I18n\Tooling\CatalogCompiler;
use Pollora\I18n\Tooling\CatalogEntry;
use Pollora\I18n\Tooling\CatalogExtractor;
use Pollora\I18n\Tooling\CatalogMerger;
use Pollora\I18n\Tooling\CatalogMessage;
use Pollora\I18n\Tooling\CompiledFormat;
use Pollora\I18n\Tooling\Console\Cli;
use Pollora\I18n\Tooling\Console\ExtractCommand;
use Pollora\I18n\Tooling\Console\Options;
use Pollora\I18n\Tooling\Console\ReportRenderer;
use Pollora\I18n\Tooling\Console\UpdateCommand;
use Pollora\I18n\Tooling\DeclarationLoader;
use Pollora\I18n\Tooling\DeclarationLocator;
use Pollora\I18n\Tooling\FileOutcome;
use Pollora\I18n\Tooling\FileResult;
use Pollora\I18n\Tooling\InvalidDeclarationException;
use Pollora\I18n\Tooling\InvalidRunException;
use Pollora\I18n\Tooling\L10nPhpWriter;
use Pollora\I18n\Tooling\LanguageFiles;
use Pollora\I18n\Tooling\MoCompiler;
use Pollora\I18n\Tooling\PluralForms;
use Pollora\I18n\Tooling\PoFileWriter;
use Pollora\I18n\Tooling\Quietly;
use Pollora\I18n\Tooling\RunReport;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

beforeEach(fn () => TranslatableRegistry::flush());

/**
 * Raises every PHP warning as an exception while the callback runs.
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function withoutWarnings(callable $callback): mixed
{
    set_error_handler(static function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    });

    try {
        return $callback();
    } finally {
        restore_error_handler();
    }
}

/**
 * Runs the callback with a umask of zero, so that the permissions asked for are the ones given.
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function withOpenUmask(callable $callback): mixed
{
    $previous = umask(0);

    try {
        return $callback();
    } finally {
        umask($previous);
    }
}

it('creates the folders of a file with full permissions, as deep as needed', function (): void {
    $directory = cliDirectory();

    withOpenUmask(fn () => (new AtomicFileWriter)->writeIfChanged($directory.'/a/b/app.mo', 'bytes'));
    clearstatcache();

    expect(fileperms($directory.'/a') & 0777)->toBe(0777)
        ->and(fileperms($directory.'/a/b') & 0777)->toBe(0777);
});

it('creates the folders of a catalogue with full permissions, as deep as needed', function (): void {
    $directory = cliDirectory();

    withOpenUmask(fn () => (new PoFileWriter)->writeIfChanged(new Catalog(['Language' => 'fr']), $directory.'/a/b/app.po'));
    clearstatcache();

    expect(fileperms($directory.'/a') & 0777)->toBe(0777)
        ->and(fileperms($directory.'/a/b') & 0777)->toBe(0777);
});

it('keeps every permission bit of a catalogue it rewrites', function (): void {
    $directory = cliDirectory();
    $path = $directory.'/app.po';
    $writer = new PoFileWriter;
    $writer->writeIfChanged(new Catalog(['Language' => 'fr']), $path);
    chmod($path, 0777);

    $writer->writeIfChanged(new Catalog(['Language' => 'de']), $path);
    clearstatcache();

    expect(fileperms($path) & 0777)->toBe(0777);
});

it('ignores a file called .po and the files that do not belong to the domain', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['.po' => '', 'a.po' => '', 'app-fr_FR.po' => '', 'app-.po' => '', 'other-de_DE.po' => '']);
    $files = new LanguageFiles;

    expect(array_keys($files->poFiles($directory)))->toBe(['a', 'app-', 'app-fr_FR', 'other-de_DE'])
        ->and(array_keys($files->poFiles($directory, 'app')))->toBe(['fr_FR']);
});

it('compiles to the path it was given, without doubling the slash', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['books-fr_FR.po' => cliFrenchPo()]);

    $report = (new CatalogCompiler)->compile($directory.'/', 'books', [CompiledFormat::Mo]);

    expect($report->files[0]->path)->toBe($directory.'/books-fr_FR.mo');
});

it('leaves a compiled file alone when it is as recent as its .po file', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['books-fr_FR.po' => cliFrenchPo(), 'books-fr_FR.mo' => 'stale']);
    cliAge($directory.'/books-fr_FR.po', 1_000_000_000);
    cliAge($directory.'/books-fr_FR.mo', 1_000_000_000);

    $report = (new CatalogCompiler)->compile($directory, 'books', [CompiledFormat::Mo]);

    expect($report->files[0]->outcome)->toBe(FileOutcome::Unchanged)
        ->and(file_get_contents($directory.'/books-fr_FR.mo'))->toBe('stale');

    cliAge($directory.'/books-fr_FR.mo', 999_999_999);

    expect((new CatalogCompiler)->compile($directory, 'books', [CompiledFormat::Mo])->files[0]->outcome)->toBe(FileOutcome::Updated);
});

it('lists the formats once each in a fixed order, and names an unknown one without its spaces', function (): void {
    expect(CompiledFormat::fromList('php'))->toBe([CompiledFormat::Php])
        ->and(CompiledFormat::fromList('php, mo,php'))->toBe([CompiledFormat::Mo, CompiledFormat::Php])
        ->and(fn () => CompiledFormat::fromList('mo,  json '))->toThrow(InvalidRunException::class, 'The format "json" is unknown');
});

it('finds the plural forms of a language whatever the case of its code', function (): void {
    expect(PluralForms::forLocale('FR_fr'))->toBe(PluralForms::forLocale('fr_FR'))
        ->and(PluralForms::forLocale('fr_FR'))->not->toBeNull();
});

it('reads a string option, and nothing from an empty one', function (): void {
    $definition = new InputDefinition([new InputOption('name', null, InputOption::VALUE_REQUIRED)]);

    expect(Options::string(new ArrayInput(['--name' => 'x'], $definition), 'name'))->toBe('x')
        ->and(Options::string(new ArrayInput(['--name' => ''], $definition), 'name'))->toBeNull()
        ->and(Options::string(new ArrayInput([], $definition), 'name'))->toBeNull();
});

it('reads the strings of a repeated option and drops the empty ones', function (): void {
    $definition = new InputDefinition([new InputOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)]);

    expect(Options::strings(new ArrayInput(['--path' => ['a', '', 'b']], $definition), 'path'))->toBe(['a', 'b'])
        ->and(Options::strings(new ArrayInput(['--path' => ['', '']], $definition), 'path'))->toBe([])
        ->and(Options::strings(new ArrayInput([], $definition), 'path'))->toBe([]);
});

it('reads only the strings of an option that holds other things too', function (): void {
    $input = new class extends ArrayInput
    {
        public function __construct()
        {
            parent::__construct([]);
        }

        public function getOption(string $name): mixed
        {
            return ['a', 5, null, '', 'b'];
        }
    };

    expect(Options::strings($input, 'x'))->toBe(['a', 'b']);
});

it('does not let the application exit the process', function (): void {
    expect((new ReflectionProperty(Application::class, 'autoExit'))->getValue(Cli::application()))->toBeFalse();
});

it('writes the diagnostics in the output itself when there is no error output', function (): void {
    $output = new BufferedOutput;

    ReportRenderer::render($output, new RunReport([new FileResult('a.po', FileOutcome::Created)], ['one problem']), false);
    ReportRenderer::error($output, 'boom');

    expect($output->fetch())->toBe("created   a.po\n1 created, 0 updated, 0 unchanged\nwarning: one problem\nerror: boom\n");
});

it('reads the repeated options of the commands from a real command line', function (): void {
    $project = cliProject(['src/Other.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('Other')]\nclass Other {}\n"]);
    cliWrite($project, [
        'one.pot' => "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"From one\"\nmsgstr \"\"\n",
        'two.pot' => "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"From two\"\nmsgstr \"\"\n",
        'more/Third.php' => "<?php\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Label;\n#[Label('Third')]\nclass Third {}\n",
        'more-declarations.php' => "<?php\nreturn [];\n",
    ]);
    $application = new Application('pollora-i18n');
    $application->setAutoExit(false);
    $application->addCommands([new ExtractCommand(basePath: $project), new UpdateCommand]);
    $output = new BufferedOutput;

    $code = $application->run(new StringInput(sprintf(
        'extract --domain books --path %1$s/src --path %1$s/more --declarations %1$s/declarations.php --declarations %1$s/more-declarations.php --merge %1$s/one.pot --merge %1$s/two.pot --output %1$s/out.pot',
        $project,
    )), $output);
    $pot = (string) file_get_contents($project.'/out.pot');

    expect($code)->toBe(0)
        ->and($pot)->toContain('msgid "Book"')->toContain('msgid "Third"')->toContain('msgid "From one"')->toContain('msgid "From two"');

    $update = $application->run(new StringInput(sprintf('update --domain books --pot %1$s/out.pot --languages %1$s/langs --locale fr_FR --locale de_DE', $project)), $output);

    expect($update)->toBe(0)
        ->and(is_file($project.'/langs/books-fr_FR.po'))->toBeTrue()
        ->and(is_file($project.'/langs/books-de_DE.po'))->toBeTrue();
});

it('takes the template of an update from --pot when it is given', function (): void {
    $project = cliDirectory();
    cliWrite($project, [
        'elsewhere.pot' => "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Here\"\nmsgstr \"\"\n",
        'langs/books.pot' => "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"There\"\nmsgstr \"\"\n",
    ]);
    $application = new Application('pollora-i18n');
    $application->setAutoExit(false);
    $application->addCommands([new UpdateCommand]);

    $application->run(new StringInput(sprintf('update --domain books --pot %1$s/elsewhere.pot --languages %1$s/langs/ --locale fr_FR', $project)), new BufferedOutput);
    $po = (string) file_get_contents($project.'/langs/books-fr_FR.po');

    expect($po)->toContain('msgid "Here"')->not->toContain('msgid "There"');

    $application->run(new StringInput(sprintf('update --domain books --languages %1$s/langs/ --locale de_DE', $project)), new BufferedOutput);

    expect((string) file_get_contents($project.'/langs/books-de_DE.po'))->toContain('msgid "There"');
});

it('reads the declarations of installed packages without a warning, Composer 1 lists included', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, [
        'composer/installed.json' => json_encode([
            ['name' => 'a/a', 'install-path' => '../a/a/', 'extra' => ['pollora' => ['i18n' => 'i18n.php']]],
            'not a package',
            ['name' => 'b/b'],
            ['name' => 'c/c', 'extra' => 'text'],
            ['name' => 'd/d', 'extra' => ['pollora' => 'text']],
        ], JSON_THROW_ON_ERROR),
    ]);

    $files = withoutWarnings(fn () => (new DeclarationLocator)->locate($directory.'/composer/installed.json'));

    expect($files)->toBe([$directory.'/a/a/i18n.php']);
});

it('reads a list of installed packages whose "packages" key is not a list', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['installed.json' => '{"packages": "oops"}']);

    expect(withoutWarnings(fn () => (new DeclarationLocator)->locate($directory.'/installed.json')))->toBe([]);
});

it('drops the empty and dot segments of an install path', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['installed.json' => json_encode(['packages' => [['name' => 'a/a', 'install-path' => '/opt//pkg/./a', 'extra' => ['pollora' => ['i18n' => 'x.php']]]]], JSON_THROW_ON_ERROR)]);

    expect((new DeclarationLocator)->locate($directory.'/installed.json'))->toBe([$directory.'/opt/pkg/a/x.php']);
});

it('refuses a package path that goes up with backslashes', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['installed.json' => json_encode(['packages' => [['name' => 'a/a', 'extra' => ['pollora' => ['i18n' => 'a\\..\\b.php']]]]], JSON_THROW_ON_ERROR)]);

    (new DeclarationLocator)->locate($directory.'/installed.json');
})->throws(InvalidDeclarationException::class, 'must be a path relative to the package');

it('names the key and the previous exception of an unusable declaration file', function (): void {
    $previous = new RuntimeException('inner');
    $failed = InvalidDeclarationException::fileNotLoadable('d.php', $previous);

    expect($failed->getCode())->toBe(0)
        ->and($failed->getPrevious())->toBe($previous)
        ->and($failed->getMessage())->toBe('The declaration file "d.php" failed to load: inner')
        ->and(InvalidDeclarationException::invalidClass('d.php', 5)->getMessage())->toBe('The declaration file "d.php" has the key 5, which is not a class name.')
        ->and(InvalidDeclarationException::invalidClass('d.php', '')->getMessage())->toBe('The declaration file "d.php" has the key \'\', which is not a class name.');
});

it('says a declaration file cannot be read when it exists but is not readable', function (): void {
    $directory = cliDirectory();
    cliWrite($directory, ['d.php' => "<?php\nreturn [];\n"]);
    chmod($directory.'/d.php', 0000);

    try {
        (new DeclarationLoader)->loadFile($directory.'/d.php');
    } catch (InvalidDeclarationException $exception) {
        expect($exception->getMessage())->toContain('does not exist or cannot be read');

        return;
    } finally {
        chmod($directory.'/d.php', 0644);
    }

    throw new RuntimeException('No exception was raised.');
})->skip(fn (): bool => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root reads every file');

it('leaves out the messages and the keys that have no usable original in a .l10n.php file', function (): void {
    $php = (new L10nPhpWriter)->render(['Zed' => '1', 'Alpha' => '2', 'messages' => 'no'], ['' => ['x'], 'a' => ['b'], "\0pl" => ['y'], 'z' => ['', 'q'], 'c' => ['d']]);

    expect($php)->toBe("<?php\nreturn [\n'alpha'=>'2',\n'zed'=>'1',\n'messages'=>[\n'a'=>'b',\n'c'=>'d',\n],\n];\n");
});

it('leaves out an empty original in a .mo file', function (): void {
    $bytes = (new MoCompiler)->compile(['Language' => 'fr'], ['' => ['x'], 'a' => ['b']]);
    $path = cliDirectory().'/t.mo';
    file_put_contents($path, $bytes);

    expect(cliReadMo($path))->toBe(['' => "Language: fr\n", 'a' => 'b']);
});

it('keeps the translations of a singular message and the flags of a new one when it merges', function (): void {
    $singular = static fn (string $id, array $translations = [], array $flags = [], ?string $plural = null): CatalogMessage => new CatalogMessage(new CatalogEntry($id, null, $plural), $translations, $flags);

    $merged = (new CatalogMerger)->mergeAll(
        new Catalog([], [$singular('Book', ['Livre'])]),
        new Catalog([], [$singular('Book', [], [], 'Books')]),
    );
    $fresh = (new CatalogMerger)->merge(new Catalog, new Catalog([], [$singular('New', [], ['fuzzy', 'php-format'])]));

    expect($merged->get('Book')?->translations)->toBe(['Livre'])
        ->and($merged->get('Book')?->entry->plural)->toBe('Books')
        ->and($fresh->get('New')?->flags)->toBe(['php-format']);
});

it('keeps the singular translation of a message found in several templates', function (): void {
    $first = new Catalog([], [new CatalogMessage(new CatalogEntry('Book'), ['Livre'])]);
    $second = new Catalog([], [new CatalogMessage(new CatalogEntry('Book'))]);

    expect((new CatalogMerger)->mergeAll($first, $second)->get('Book')?->translations)->toBe(['Livre']);
});

it('writes every header of a template and says what it found when nothing is declared', function (): void {
    $project = cliDirectory();
    cliWrite($project, ['src/Broken.php' => "<?php\n#[Foo(new Translatable('x'))]\nclass {\n"]);

    $report = (new CatalogExtractor)->extract(domain: 'books', paths: [$project.'/src'], output: $project.'/out.pot', basePath: $project);
    $pot = (string) file_get_contents($project.'/out.pot');

    expect($pot)->toContain('"Project-Id-Version: books\n"')
        ->toContain('"MIME-Version: 1.0\n"')
        ->toContain('"Content-Type: text/plain; charset=UTF-8\n"')
        ->toContain('"Content-Transfer-Encoding: 8bit\n"')
        ->toContain('"Plural-Forms: nplurals=2; plural=(n != 1);\n"')
        ->toContain('"X-Domain: books\n"')
        ->and($report->diagnostics)->toHaveCount(2)
        ->and($report->diagnostics[0])->toStartWith('No translatable attribute is declared')
        ->and($report->diagnostics[1])->toContain('Broken.php:3: The file could not be parsed');
});

it('swallows the warning of the call it runs, and only that one', function (): void {
    $seen = [];
    set_error_handler(static function (int $level, string $message) use (&$seen): bool {
        $seen[] = $message;

        return true;
    });

    try {
        error_clear_last();
        $result = Quietly::call(static fn (): mixed => fopen('/nonexistent/pollora-i18n/file', 'rb'));
        $lastDuring = error_get_last();
        // Not a filesystem call: how many warnings a failed one raises depends on the wrapper on `file` (two with
        // the one Pest runs a mutant under), and this only asks whether the previous handler is back
        trigger_error('raised after the quiet call', E_USER_WARNING);
    } finally {
        restore_error_handler();
    }

    expect($result)->toBeFalse()
        ->and($lastDuring)->toBeNull()
        ->and($seen)->toHaveCount(1)
        ->and($seen[0])->toBe('raised after the quiet call');
});
