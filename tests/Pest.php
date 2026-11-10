<?php

declare(strict_types=1);

/**
 * Runs tests/Fixtures/request.php, one simulated web request, in a process of its own.
 *
 * @return array{result: string, tooling: list<string>}
 */
function simulateRequest(string $mode): array
{
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', __DIR__.'/Fixtures/request.php', $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('The simulated request could not start.');
    }

    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException('The simulated request failed: '.$errors.$output);
    }

    /** @var array{result: string, tooling: list<string>} */
    return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Temporary folders of the command tests, removed after each test.
 *
 * @var list<string>
 */
$GLOBALS['cliTemporaryDirectories'] = [];

afterEach(function (): void {
    foreach ($GLOBALS['cliTemporaryDirectories'] as $directory) {
        cliRemove($directory);
    }

    $GLOBALS['cliTemporaryDirectories'] = [];
});

function cliRemove(string $path): void
{
    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }

    foreach (is_dir($path) ? (scandir($path) ?: []) : [] as $name) {
        if ($name !== '.' && $name !== '..') {
            cliRemove($path.'/'.$name);
        }
    }

    is_dir($path) && rmdir($path);
}

/**
 * A new empty folder, removed when the test ends.
 */
function cliDirectory(): string
{
    $directory = sys_get_temp_dir().'/pollora-i18n-cli-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $GLOBALS['cliTemporaryDirectories'][] = $directory;

    return $directory;
}

/**
 * Writes files under a folder; the keys are relative paths.
 *
 * @param  array<string, string>  $files
 */
function cliWrite(string $directory, array $files): void
{
    foreach ($files as $path => $contents) {
        is_dir(dirname($directory.'/'.$path)) || mkdir(dirname($directory.'/'.$path), 0777, true);
        file_put_contents($directory.'/'.$path, $contents);
    }
}

/**
 * A project with two translatable attributes and the file that declares them.
 *
 * @param  array<string, string>  $more  Further files
 */
function cliProject(array $more = []): string
{
    $directory = cliDirectory();
    $namespace = 'Pollora\\\\I18n\\\\Tests\\\\Fixtures\\\\Scan';

    cliWrite($directory, [
        'src/Book.php' => <<<'PHP'
<?php

declare(strict_types=1);

use Pollora\I18n\Tests\Fixtures\Scan\Label;
use Pollora\I18n\Tests\Fixtures\Scan\PostType;

#[PostType('Book', plural: 'Books')]
#[Label('Hello')]
final class Book {}

#[PostType('Film', textDomain: 'other')]
final class Film {}
PHP,
        'declarations.php' => "<?php\n\nreturn [\n    '{$namespace}\\\\PostType' => ['singular', 'plural'],\n    '{$namespace}\\\\Label' => ['value'],\n];\n",
        ...$more,
    ]);

    return $directory;
}

/**
 * Runs bin/pollora-i18n in a process of its own.
 *
 * @param  list<string>  $arguments
 * @return array{code: int, out: string, err: string}
 */
function cliRun(array $arguments, string $directory, ?string $binary = null): array
{
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', $binary ?? dirname(__DIR__).'/bin/pollora-i18n', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);

    if (! is_resource($process)) {
        throw new RuntimeException('The binary could not start.');
    }

    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'out' => $out, 'err' => $err];
}

/**
 * Size, modification time and content hash of every file under a folder.
 *
 * @return array<string, string>
 */
function cliSnapshot(string $directory): array
{
    clearstatcache();
    $snapshot = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        $snapshot[substr($file->getPathname(), strlen($directory))] = filemtime($file->getPathname()).':'.md5_file($file->getPathname());
    }

    ksort($snapshot);

    return $snapshot;
}

/**
 * Reads a .mo file the way a gettext reader does.
 *
 * @return array<string, string> The translation (forms joined by NUL) by original
 */
function cliReadMo(string $path): array
{
    $bytes = (string) file_get_contents($path);
    $header = unpack('Vmagic/Vrevision/Vcount/Voriginals/Vtranslations', $bytes);
    $messages = [];

    for ($index = 0; $index < $header['count']; $index++) {
        $original = unpack('Vlength/Voffset', $bytes, $header['originals'] + $index * 8);
        $translation = unpack('Vlength/Voffset', $bytes, $header['translations'] + $index * 8);
        $messages[substr($bytes, $original['offset'], $original['length'])] = substr($bytes, $translation['offset'], $translation['length']);
    }

    return $messages;
}

/**
 * A French .po file with a translated, a fuzzy, an untranslated and an obsolete message.
 */
function cliFrenchPo(): string
{
    return <<<'PO'
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Language: fr_FR\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"

msgid "Book"
msgstr "Livre"

#, fuzzy
msgid "Fuzzy"
msgstr "Flou"

msgid "Empty"
msgstr ""

msgctxt "menu"
msgid "File"
msgid_plural "Files"
msgstr[0] "Fichier"
msgstr[1] "Fichiers"

#~ msgid "Gone"
#~ msgstr "Parti"

PO;
}

/**
 * Sets the modification time of a file.
 */
function cliAge(string $path, int $time): void
{
    touch($path, $time);
    clearstatcache();
}
