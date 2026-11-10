<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\MoCompiler;

/**
 * @return array{headers: array<string, string>, messages: array<array-key, list<string>>, po: string}
 */
function moSample(): array
{
    return require __DIR__.'/../Fixtures/sample.php';
}

/**
 * Reads a .mo file back by hand: header fields, then the originals and translations tables.
 *
 * @return array{magic: int, revision: int, hashSize: int, entries: array<string, string>, originals: list<string>}
 */
function readMo(string $bytes): array
{
    /** @var array{1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int} $head */
    $head = unpack('V7', $bytes);
    $entries = [];
    $originals = [];

    for ($i = 0; $i < $head[3]; $i++) {
        /** @var array{length: int, offset: int} $original */
        $original = unpack('Vlength/Voffset', $bytes, $head[4] + $i * 8);
        /** @var array{length: int, offset: int} $translation */
        $translation = unpack('Vlength/Voffset', $bytes, $head[5] + $i * 8);

        expect($bytes[$original['offset'] + $original['length']])->toBe("\0")
            ->and($bytes[$translation['offset'] + $translation['length']])->toBe("\0");

        $originals[] = substr($bytes, $original['offset'], $original['length']);
        $entries[end($originals)] = substr($bytes, $translation['offset'], $translation['length']);
    }

    return ['magic' => $head[1], 'revision' => $head[2], 'hashSize' => $head[6], 'entries' => $entries, 'originals' => $originals];
}

it('writes a little-endian revision 0 file without a hash table', function (): void {
    $sample = moSample();

    $mo = readMo((new MoCompiler)->compile($sample['headers'], $sample['messages']));

    expect($mo['magic'])->toBe(0x950412DE)
        ->and($mo['revision'])->toBe(0)
        ->and($mo['hashSize'])->toBe(0);
});

it('stores the headers under the empty original and sorts the originals by byte for the binary search', function (): void {
    $sample = moSample();

    $mo = readMo((new MoCompiler)->compile($sample['headers'], $sample['messages']));

    $sorted = $mo['originals'];
    usort($sorted, strcmp(...));

    expect($mo['originals'][0])->toBe('')
        ->and($mo['originals'])->toBe($sorted)
        ->and($mo['entries'][''])->toBe("Content-Type: text/plain; charset=UTF-8\nLanguage: fr_FR\nPlural-Forms: nplurals=2; plural=(n > 1);\n");
});

it('keeps contexts and plural forms in the raw gettext shape', function (): void {
    $sample = moSample();

    $mo = readMo((new MoCompiler)->compile($sample['headers'], $sample['messages']));

    expect($mo['entries']["post type\x04Book"])->toBe('Ouvrage')
        ->and($mo['entries']["%d book\0%d books"])->toBe("%d livre\0%d livres")
        ->and($mo['entries']["menu\x04File\0Files"])->toBe("Fichier\0Fichiers")
        ->and($mo['entries']['42'])->toBe('quarante-deux')
        ->and($mo['entries']['Café ☕'])->toBe('Café ☕ — déjà vu, 日本語');
});

it('leaves out untranslated messages, partly translated plurals and messages without forms', function (): void {
    $sample = moSample();

    $mo = readMo((new MoCompiler)->compile($sample['headers'], $sample['messages']));

    expect($mo['originals'])->not->toContain('Untranslated')
        ->not->toContain("%d cat\0%d cats")
        ->not->toContain('No forms');
});

it('ignores a message with the empty original, which the headers own', function (): void {
    $mo = readMo((new MoCompiler)->compile(['Language' => 'fr'], ['' => ['intrus'], 'A' => ['B']]));

    expect($mo['entries'])->toBe(['' => "Language: fr\n", 'A' => 'B']);
});

it('writes no header entry when there are no headers', function (): void {
    $mo = readMo((new MoCompiler)->compile([], ['A' => ['B']]));

    expect($mo['originals'])->toBe(['A']);
});

it('writes an empty but valid file for an empty catalogue', function (): void {
    $bytes = (new MoCompiler)->compile([], []);
    $mo = readMo($bytes);

    expect(strlen($bytes))->toBe(28)
        ->and($mo['originals'])->toBe([]);
});

it('produces the same bytes whatever the order of the input', function (): void {
    $sample = moSample();
    $compiler = new MoCompiler;

    $shuffled = $sample['messages'];
    krsort($shuffled, SORT_STRING);

    expect($compiler->compile($sample['headers'], $shuffled))->toBe($compiler->compile($sample['headers'], $sample['messages']));
});

/**
 * Runs a gettext tool and returns what it printed.
 */
function runGettext(string $command): string
{
    return (string) shell_exec($command.' 2>&1');
}

it('matches the bytes of msgfmt --no-hash', function (): void {
    if (trim((string) shell_exec('command -v msgfmt')) === '') {
        $this->markTestSkipped('msgfmt is not installed.');
    }

    $sample = moSample();
    $directory = sys_get_temp_dir().'/pollora-i18n-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory.'/sample.po', $sample['po']);

    try {
        $output = runGettext('msgfmt --no-hash -o '.escapeshellarg($directory.'/reference.mo').' '.escapeshellarg($directory.'/sample.po'));

        expect($output)->toBe('')
            ->and((new MoCompiler)->compile($sample['headers'], $sample['messages']))->toBe(file_get_contents($directory.'/reference.mo'));
    } finally {
        array_map('unlink', glob($directory.'/*') ?: []);
        rmdir($directory);
    }
});

it('is read by msgunfmt like the file msgfmt writes, hash table included', function (): void {
    if (trim((string) shell_exec('command -v msgfmt msgunfmt')) === '') {
        $this->markTestSkipped('msgfmt and msgunfmt are not installed.');
    }

    $sample = moSample();
    $directory = sys_get_temp_dir().'/pollora-i18n-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory.'/sample.po', $sample['po']);
    file_put_contents($directory.'/mine.mo', (new MoCompiler)->compile($sample['headers'], $sample['messages']));

    try {
        runGettext('msgfmt -o '.escapeshellarg($directory.'/reference.mo').' '.escapeshellarg($directory.'/sample.po'));

        $reference = runGettext('msgunfmt '.escapeshellarg($directory.'/reference.mo'));
        $mine = runGettext('msgunfmt '.escapeshellarg($directory.'/mine.mo'));

        expect($mine)->toBe($reference)
            ->and($mine)->toContain('msgid "%d book"');
    } finally {
        array_map('unlink', glob($directory.'/*') ?: []);
        rmdir($directory);
    }
});
