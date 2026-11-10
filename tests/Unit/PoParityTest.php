<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\PoReader;
use Pollora\I18n\Tooling\PoWriter;

/**
 * Runs a GNU gettext tool, or returns null when it is not installed.
 *
 * @param  list<string>  $command
 * @return array{int, string, string}|null
 */
function gettextTool(array $command): ?array
{
    $finder = trim((string) shell_exec('command -v '.escapeshellarg($command[0]).' 2>/dev/null'));

    if ($finder === '') {
        return null;
    }

    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $output, $errors];
}

beforeEach(function (): void {
    if (gettextTool(['msgfmt', '--version']) === null || gettextTool(['msgcat', '--version']) === null) {
        $this->markTestSkipped('GNU gettext (msgfmt, msgcat) is not installed.');
    }

    $this->path = sys_get_temp_dir().'/pollora-i18n-parity-'.bin2hex(random_bytes(4)).'.po';
    file_put_contents($this->path, (new PoWriter)->write(fullCatalog()));
});

afterEach(function (): void {
    if (isset($this->path) && is_file($this->path)) {
        unlink($this->path);
    }
});

it('writes a file msgfmt accepts', function (): void {
    [$status, , $errors] = gettextTool(['msgfmt', '--check-format', '--check-domain', '-o', '/dev/null', $this->path]);

    expect($errors)->toBe('')->and($status)->toBe(0);
});

it('writes a file msgcat reads to the same catalogue', function (): void {
    [$status, $output] = gettextTool(['msgcat', '--no-wrap', '--no-location', $this->path]);
    $original = (new PoReader)->readString((string) file_get_contents($this->path));
    $roundTripped = (new PoReader)->readString($output);

    expect($status)->toBe(0);

    foreach ($original->messages() as $message) {
        $other = $roundTripped->get($message->key());

        expect($other?->translations)->toBe($message->translations)
            ->and($other?->entry->plural)->toBe($message->entry->plural)
            ->and($other?->obsolete)->toBe($message->obsolete);
    }

    expect($roundTripped)->toHaveCount(count($original))
        ->and($roundTripped->headers)->toBe($original->headers);
});
