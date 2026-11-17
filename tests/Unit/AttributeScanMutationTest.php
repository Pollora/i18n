<?php

declare(strict_types=1);

use Pollora\I18n\Tests\Fixtures\Scan\Tags;
use Pollora\I18n\Tooling\AttributeExtraction;
use Pollora\I18n\Tooling\AttributeFileScanner;

const SCAN_TAGS = 'Pollora\I18n\Tests\Fixtures\Scan\Tags';

/**
 * @return array{records: list<array<int, mixed>>, marked: list<array<int, mixed>>, diagnostics: list<array{int, string}>}
 */
function scanTags(string $code): array
{
    /** @var array{records: list<array<int, mixed>>, marked: list<array<int, mixed>>, diagnostics: list<array{int, string}>} */
    return (new AttributeFileScanner)->scan(
        "<?php\nuse Pollora\\I18n\\Runtime\\Translatable as T;\nuse Pollora\\I18n\\Tests\\Fixtures\\Scan\\Tags;\n".$code,
        [Tags::class => ['names'], 'Label' => ['value']],
    );
}

it('reports a file it cannot parse with the line and the whole shape of a result', function (): void {
    expect((new AttributeFileScanner)->scan("<?php\n\nclass {", []))->toBe([
        'records' => [],
        'marked' => [],
        'diagnostics' => [[3, "The file could not be parsed: Syntax error, unexpected '{', expecting T_STRING"]],
    ]);
});

it('reports a parse error on the first line as line 1', function (): void {
    expect((new AttributeFileScanner)->scan('<?php class {', [])['diagnostics'][0][0])->toBe(1);
});

it('keeps one parser for all the files it scans', function (): void {
    $scanner = new AttributeFileScanner;
    $parser = new ReflectionProperty($scanner, 'parser');

    $scanner->scan('<?php class A {}', []);
    $first = $parser->getValue($scanner);
    $scanner->scan('<?php class B {}', []);

    expect($first)->not->toBeNull()->and($parser->getValue($scanner))->toBe($first);
});

it('numbers the paths of the items without a key from zero', function (): void {
    $result = scanTags("#[Tags([new T('a'), new T('b'), new T('c')])]\nclass A {}");

    expect(array_column($result['marked'], 1))->toBe(['0', '1', '2']);
});

it('takes a key after an explicit integer key from the next integer', function (): void {
    $result = scanTags("#[Tags([5 => new T('a'), new T('b'), 'k' => new T('c'), new T('d')])]\nclass A {}");

    expect(array_column($result['marked'], 1))->toBe(['5', '6', 'k', '7']);
});

it('keeps reading the items of a declared field after a marker', function (): void {
    $result = scanTags("#[Tags(['x', new T('y'), 'z'])]\nclass A {}");

    expect(array_column($result['records'], 2))->toBe(['x', 'z'])
        ->and(array_column($result['marked'], 2))->toBe(['y']);
});

it('leaves a marker outside any attribute alone', function (): void {
    $result = scanTags("function f() { return [new T('Outside')]; }\nclass A { public array \$list = [new T('Property')]; }");

    expect($result)->toBe(['records' => [], 'marked' => [], 'diagnostics' => []]);
});

it('does not read a concatenation with a part that is not a literal', function (): void {
    $result = scanTags("#[Tags(['a'.FOO, FOO.'b', 'c'.'d'])]\nclass A {}");

    expect(array_column($result['records'], 2))->toBe(['cd'])
        ->and($result['diagnostics'])->toHaveCount(2);
});

it('treats NULL in capitals like null', function (): void {
    expect(scanTags("#[Tags(NULL)]\nclass A {}"))->toBe(['records' => [], 'marked' => [], 'diagnostics' => []]);
});

it('names a class of the global namespace whole in a message', function (): void {
    $result = scanTags("#[Label(value: \$x)]\nclass A {}");

    expect($result['diagnostics'])->toBe([[4, 'The "value" argument of #[Label] is not a literal string: it was skipped.']]);
});

it('gives the line of the expression that is not a literal, not the line of the new', function (): void {
    $result = scanTags("#[Tags([new T(\n\$x\n)])]\nclass A {}");

    expect($result['diagnostics'])->toBe([[5, 'The text of a Translatable in #[Tags] is not a literal string: it was skipped.']]);
});

it('raises no PHP warning while it reads literals, arrays and markers', function (): void {
    set_error_handler(static function (int $level, string $message): never {
        throw new ErrorException($message, 0, $level);
    });

    try {
        $result = scanTags("#[Tags(['a', 'b' => 'c', new T('d'), 3 => 'e', 1.5, null])]\nclass A {}");
    } finally {
        restore_error_handler();
    }

    expect($result['records'])->not->toBeEmpty();
});

it('lists the domains of an extraction, as strings even when a domain is a number', function (): void {
    $extraction = new AttributeExtraction(['404' => [], 'books' => []], [], 0, 0);

    expect($extraction->domains())->toBe(['404', 'books']);
});
