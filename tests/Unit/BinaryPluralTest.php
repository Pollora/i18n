<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\Console\ExitCode;
use Pollora\I18n\Tooling\PoReader;

/**
 * A project with a plural, a plural with a context, a text, each with a note for the translators, and a plural with none.
 */
function extractPluralProject(): string
{
    $directory = cliDirectory();

    cliWrite($directory, ['src/Library.php' => <<<'PHP'
<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\Translatable as T;
use Pollora\I18n\Runtime\TranslatablePlural as P;

#[Counts([
    'books' => new P('One book', '%d books', comment: 'Number of books on a shelf'),
    'films' => new P(singular: 'One film', plural: '%d films', context: 'cinema', comment: 'Number of films'),
    'author' => new T('Written by', comment: 'Byline of an article'),
    'shelves' => new P('One shelf', '%d shelves', 'other'),
])]
final class Library {}
PHP]);

    return $directory;
}

/**
 * Where WordPress is installed, for the check with its own reader: POLLORA_WORDPRESS_PATH, or the public/cms folder of a Pollora project in DDEV.
 */
function extractPluralWordPress(): ?string
{
    foreach ([getenv('POLLORA_WORDPRESS_PATH'), '/var/www/html/public/cms'] as $path) {
        if (is_string($path) && is_file($path.'/wp-includes/pomo/mo.php')) {
            return $path;
        }
    }

    return null;
}

it('writes the plural form, the context and the notes of the translators in the template', function (): void {
    $project = extractPluralProject();

    $run = cliRun(['extract', '--domain=library', '--path=src', '--cache=build/cache.php'], $project);
    $catalog = (new PoReader)->read($project.'/languages/library.pot');
    $books = $catalog->get('One book');
    $films = $catalog->get("cinema\x04One film");
    $author = $catalog->get('Written by');

    expect($run['code'])->toBe(ExitCode::SUCCESS)
        ->and($run['err'])->toBe('')
        ->and($catalog->count())->toBe(3)
        ->and($books?->entry->plural)->toBe('%d books')
        ->and($books?->entry->comments)->toBe(['Number of books on a shelf', 'Attribute Counts › books'])
        ->and($films?->entry->msgctxt)->toBe('cinema')
        ->and($films?->entry->plural)->toBe('%d films')
        ->and($films?->entry->comments)->toBe(['Number of films', 'Attribute Counts › films'])
        ->and($author?->entry->plural)->toBeNull()
        ->and($author?->entry->comments)->toBe(['Byline of an article', 'Attribute Counts › author'])
        ->and($catalog->has('One shelf'))->toBeFalse();

    $pot = (string) file_get_contents($project.'/languages/library.pot');

    expect($pot)->toContain("#. Number of books on a shelf\n#. Attribute Counts › books\n#: src/Library.php:9\nmsgid \"One book\"\nmsgid_plural \"%d books\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n")
        ->and($pot)->toContain("msgctxt \"cinema\"\nmsgid \"One film\"\nmsgid_plural \"%d films\"");
});

it('goes from a plural in an attribute to a compiled file with three forms, and stops writing the second time', function (): void {
    $project = extractPluralProject();
    cliRun(['extract', '--domain=library', '--path=src', '--cache=build/cache.php'], $project);

    $update = cliRun(['update', '--domain=library', '--locale=ru_RU'], $project);
    $russian = (string) file_get_contents($project.'/languages/library-ru_RU.po');

    expect($update['code'])->toBe(ExitCode::SUCCESS)
        ->and($russian)->toContain('nplurals=3;')
        ->and($russian)->toContain("msgid \"One book\"\nmsgid_plural \"%d books\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"\n");

    file_put_contents($project.'/languages/library-ru_RU.po', str_replace(
        ["msgid_plural \"%d books\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"", "msgid_plural \"%d films\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\nmsgstr[2] \"\"", "msgid \"Written by\"\nmsgstr \"\""],
        ["msgid_plural \"%d books\"\nmsgstr[0] \"%d книга\"\nmsgstr[1] \"%d книги\"\nmsgstr[2] \"%d книг\"", "msgid_plural \"%d films\"\nmsgstr[0] \"%d фильм\"\nmsgstr[1] \"%d фильма\"\nmsgstr[2] \"%d фильмов\"", "msgid \"Written by\"\nmsgstr \"Автор\""],
        $russian,
    ));

    $compile = cliRun(['compile', '--domain=library'], $project);
    $mo = cliReadMo($project.'/languages/library-ru_RU.mo');

    expect($compile['code'])->toBe(ExitCode::SUCCESS)
        ->and($mo["One book\0%d books"])->toBe("%d книга\0%d книги\0%d книг")
        ->and($mo["cinema\x04One film\0%d films"])->toBe("%d фильм\0%d фильма\0%d фильмов")
        ->and($mo['Written by'])->toBe('Автор')
        ->and(array_key_exists('One shelf', $mo))->toBeFalse();

    $wordpress = extractPluralWordPress();

    if ($wordpress !== null) {
        foreach (['pomo/entry', 'pomo/translations', 'pomo/streams', 'pomo/plural-forms', 'pomo/mo'] as $file) {
            require_once $wordpress.'/wp-includes/'.$file.'.php';
        }

        $reader = new MO;
        $reader->import_from_file($project.'/languages/library-ru_RU.mo');

        expect($reader->translate_plural('One book', '%d books', 21))->toBe('%d книга')
            ->and($reader->translate_plural('One book', '%d books', 3))->toBe('%d книги')
            ->and($reader->translate_plural('One book', '%d books', 11))->toBe('%d книг')
            ->and($reader->translate_plural('One film', '%d films', 5, 'cinema'))->toBe('%d фильмов')
            ->and($reader->translate('Written by'))->toBe('Автор');
    }

    $again = [
        cliRun(['extract', '--domain=library', '--path=src', '--cache=build/cache.php'], $project)['out'],
        cliRun(['update', '--domain=library', '--locale=ru_RU'], $project)['out'],
        cliRun(['compile', '--domain=library'], $project)['out'],
    ];

    expect($again)->toBe([
        "unchanged languages/library.pot\n0 created, 0 updated, 1 unchanged\n",
        "unchanged languages/library-ru_RU.po\n0 created, 0 updated, 1 unchanged\n",
        "unchanged languages/library-ru_RU.mo\nunchanged languages/library-ru_RU.l10n.php\n0 created, 0 updated, 2 unchanged\n",
    ]);
});

it('reports a conflict and an empty context with their file and line, leaves the string out, and fails with --strict', function (): void {
    $project = cliDirectory();
    cliWrite($project, ['src/Mixed.php' => <<<'PHP'
<?php

declare(strict_types=1);

use Pollora\I18n\Runtime\TranslatablePlural as P;

#[Counts(['a' => new P('One film', '%d films'), 'b' => new P('One film', '%d movies')])]
#[Counts(['c' => new P('Empty', 'Empties', context: '')])]
final class Mixed {}
PHP]);
    $arguments = ['extract', '--domain=library', '--path=src'];

    $lenient = cliRun($arguments, $project);
    $strict = cliRun([...$arguments, '--strict'], $project);
    $pot = (string) file_get_contents($project.'/languages/library.pot');

    expect($lenient['code'])->toBe(ExitCode::SUCCESS)
        ->and($lenient['err'])->toContain('warning: src/Mixed.php:7: Conflict: "One film" is written with the plural form "%d movies" here but with the plural form "%d films" at src/Mixed.php:7: the first one was kept and this one left out.')
        ->and($lenient['err'])->toContain('warning: src/Mixed.php:8: A TranslatablePlural in #[Counts] has an empty context: it was skipped.')
        ->and($strict['code'])->toBe(ExitCode::DIAGNOSTICS)
        ->and($pot)->toContain('msgid_plural "%d films"')
        ->and($pot)->not->toContain('%d movies')
        ->and($pot)->not->toContain('Empty');
});
