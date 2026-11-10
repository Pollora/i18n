<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Parses one PHP file and returns the literal strings of its translatable attributes.
 *
 * @phpstan-import-type Record from AttributeCache
 * @phpstan-import-type Marked from AttributeCache
 * @phpstan-import-type Diagnostic from AttributeCache
 */
final class AttributeFileScanner
{
    private ?Parser $parser = null;

    /**
     * @param  array<class-string, list<string>>  $fields
     * @return array{records: list<Record>, marked: list<Marked>, diagnostics: list<Diagnostic>}
     */
    public function scan(string $code, array $fields): array
    {
        $visitor = new AttributeVisitor($fields);
        $traverser = new NodeTraverser(new NameResolver, $visitor);

        try {
            $this->parser ??= (new ParserFactory)->createForNewestSupportedVersion();
            $traverser->traverse($this->parser->parse($code) ?? []);
        } catch (Error $error) {
            return ['records' => [], 'marked' => [], 'diagnostics' => [[max($error->getStartLine(), 1), 'The file could not be parsed: '.$error->getRawMessage()]]];
        }

        return ['records' => $visitor->records, 'marked' => $visitor->marked, 'diagnostics' => $visitor->diagnostics];
    }
}
