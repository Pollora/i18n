<?php

declare(strict_types=1);

use Pollora\I18n\Tooling\L10nPhpWriter;

/**
 * Includes rendered PHP the way WordPress does, from a file.
 *
 * @return array<string, mixed>
 */
function includeRendered(string $php): array
{
    $path = tempnam(sys_get_temp_dir(), 'l10n');
    file_put_contents($path, $php);

    try {
        /** @var array<string, mixed> */
        return include $path;
    } finally {
        unlink($path);
    }
}

/**
 * True when the PHP holds nothing but a return of an array of literal strings: no call, constant or concatenation.
 */
function literalOnly(string $php): bool
{
    foreach (token_get_all($php) as $token) {
        if ($token === '.' || (is_array($token) && in_array($token[0], [T_STRING, T_EVAL, T_NAME_QUALIFIED, T_CONCAT_EQUAL], true))) {
            return false;
        }
    }

    return true;
}

it('renders a literal return with no call, no concatenation and no serialisation', function (): void {
    $sample = require __DIR__.'/../Fixtures/sample.php';

    $php = (new L10nPhpWriter)->render($sample['headers'], $sample['messages']);

    expect($php)->toStartWith("<?php\nreturn [\n")
        ->and($php)->toEndWith("];\n")
        ->and($php)->not->toContain('serialize')
        ->and($php)->not->toContain('eval')
        ->and(literalOnly($php))->toBeTrue()
        ->and(preg_match('/[\x00-\x09\x0b-\x1f\x7f]/', $php))->toBe(0);
});

it('lowers the header names and sorts them, then lists the messages by singular', function (): void {
    $sample = require __DIR__.'/../Fixtures/sample.php';

    $data = includeRendered((new L10nPhpWriter)->render($sample['headers'], $sample['messages']));

    expect(array_keys($data))->toBe(['content-type', 'language', 'plural-forms', 'messages'])
        ->and($data['language'])->toBe('fr_FR');

    /** @var array<string, string> $messages */
    $messages = $data['messages'];

    expect($messages["post type\x04Book"])->toBe('Ouvrage')
        ->and($messages['%d book'])->toBe("%d livre\0%d livres")
        ->and($messages["menu\x04File"])->toBe("Fichier\0Fichiers")
        ->and($messages['Quote "double" and \'single\' and back\\slash and $dollar and {$brace}'])->toBe('Guillemets "doubles", \'simples\', \\ et $dollar et {$accolade}')
        ->and($messages["Tab\there\nline"])->toBe("Tab\tici\nligne")
        ->and($messages['Café ☕'])->toBe('Café ☕ — déjà vu, 日本語')
        ->and($messages[42])->toBe('quarante-deux')
        ->and($messages)->not->toHaveKey('Untranslated')
        ->and($messages)->not->toHaveKey('%d cat')
        ->and($messages)->not->toHaveKey('No forms');
});

it('sorts the messages by byte and renders the same bytes whatever the order of the input', function (): void {
    $writer = new L10nPhpWriter;
    $messages = ['b' => ['2'], 'a' => ['1'], 'B' => ['3'], '10' => ['4'], '9' => ['5']];
    $reversed = array_reverse($messages, true);

    $data = includeRendered($writer->render([], $messages));

    expect(array_map('strval', array_keys($data['messages'])))->toBe(['10', '9', 'B', 'a', 'b'])
        ->and($writer->render(['A' => 'x'], $reversed))->toBe($writer->render(['A' => 'x'], $messages));
});

it('escapes backslashes and quotes in plain strings and controls in the others', function (): void {
    $php = (new L10nPhpWriter)->render([], [
        "it's \\ here" => ['x'],
        "ctx\x04k\$" => ['"$a" {$b}', 'two'],
        "back\\slash\nline" => ['x'],
    ]);

    expect($php)->toContain("'it\\'s \\\\ here'=>'x',")
        ->and($php)->toContain('"ctx\x04k\$"=>"\"\$a\" {\$b}\x00two",')
        ->and($php)->toContain('"back\\\\slash\\nline"=>\'x\',');
});

it('writes controls that have no short escape as hexadecimal and keeps carriage returns visible', function (): void {
    $text = "a\rb\x1fc\x7fd";

    $data = includeRendered((new L10nPhpWriter)->render([], [$text => [$text]]));

    expect($data['messages'])->toBe([$text => $text]);
});

it('never lets a header named messages replace the messages', function (): void {
    $data = includeRendered((new L10nPhpWriter)->render(['Messages' => 'oops', 'Language' => 'fr'], ['A' => ['B']]));

    expect($data['messages'])->toBe(['A' => 'B'])
        ->and($data['language'])->toBe('fr');
});

it('renders an empty catalogue as a valid file', function (): void {
    expect(includeRendered((new L10nPhpWriter)->render([], [])))->toBe(['messages' => []]);
});
