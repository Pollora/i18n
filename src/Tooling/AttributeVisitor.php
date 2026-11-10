<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use Throwable;

/**
 * Collects the literal strings of the translatable fields of the attributes it meets.
 * Names must already be resolved (NameResolver runs first).
 *
 * @phpstan-import-type Record from AttributeCache
 * @phpstan-import-type Diagnostic from AttributeCache
 */
final class AttributeVisitor extends NodeVisitorAbstract
{
    /** @var list<Record> */
    public array $records = [];

    /** @var list<Diagnostic> */
    public array $diagnostics = [];

    /** @var array<string, list<string>> */
    private array $parameterNames = [];

    /**
     * @param  array<class-string, list<string>>  $fields
     */
    public function __construct(private readonly array $fields) {}

    public function enterNode(Node $node): null
    {
        if (! $node instanceof Node\Attribute) {
            return null;
        }

        $class = $node->name->toString();
        $translatable = $this->fields[$class] ?? null;

        if ($translatable === null) {
            return null;
        }

        $values = $this->argumentsByName($node, $class);

        $domain = null;

        if (isset($values['textDomain']) && ! $this->isNull($values['textDomain'])) {
            $domain = $this->fold($values['textDomain']);

            if ($domain === null) {
                $this->diagnostics[] = [$node->getStartLine(), sprintf('The text domain of #[%s] is not a literal string: its strings were skipped.', $this->shortName($class))];

                return null;
            }
        }

        foreach ($translatable as $field) {
            if (isset($values[$field])) {
                $this->collect($class, $field, $values[$field], $domain);
            }
        }

        return null;
    }

    /**
     * @return array<string, Expr>
     */
    private function argumentsByName(Node\Attribute $attribute, string $class): array
    {
        $values = [];
        $names = null;

        foreach ($attribute->args as $position => $argument) {
            if ($argument->name !== null) {
                $values[$argument->name->toString()] = $argument->value;

                continue;
            }

            $names ??= $this->parameterNamesOf($class);
            $name = $names[$position] ?? null;

            if ($name === null) {
                $this->diagnostics[] = [$argument->getStartLine(), sprintf('Argument %d of #[%s] cannot be matched to a parameter (is the class loadable?): it was skipped.', $position + 1, $this->shortName($class))];

                continue;
            }

            $values[$name] = $argument->value;
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function parameterNamesOf(string $class): array
    {
        if (isset($this->parameterNames[$class])) {
            return $this->parameterNames[$class];
        }

        $names = [];

        try {
            if (class_exists($class)) {
                foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
                    $names[] = $parameter->getName();
                }
            }
        } catch (Throwable) {
            // A class that cannot be loaded has no parameters to match; each positional argument is reported.
        }

        return $this->parameterNames[$class] = $names;
    }

    private function collect(string $class, string $field, Expr $expression, ?string $domain): void
    {
        if ($this->isNull($expression)) {
            return;
        }

        $items = $expression instanceof Expr\Array_
            ? array_map(static fn (Node\ArrayItem $item): Expr => $item->value, $expression->items)
            : [$expression];

        foreach ($items as $item) {
            $text = $this->fold($item);

            if ($text === null) {
                $this->diagnostics[] = [$item->getStartLine(), sprintf('The "%s" argument of #[%s] is not a literal string: it was skipped.', $field, $this->shortName($class))];
            } elseif ($text !== '') {
                $this->records[] = [$class, $field, $text, $domain, $item->getStartLine()];
            }
        }
    }

    private function fold(Expr $expression): ?string
    {
        if ($expression instanceof String_) {
            return $expression->value;
        }

        if ($expression instanceof Concat) {
            $left = $this->fold($expression->left);
            $right = $this->fold($expression->right);

            return $left === null || $right === null ? null : $left.$right;
        }

        return null;
    }

    private function isNull(Expr $expression): bool
    {
        return $expression instanceof ConstFetch && strtolower($expression->name->toString()) === 'null';
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
