<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use ReflectionClass;
use Throwable;

/**
 * Collects the literal strings of the translatable fields of the attributes it meets, and the
 * literal text of every `new Translatable(...)` written in the arguments of any attribute.
 * Names must already be resolved (NameResolver runs first).
 *
 * @phpstan-import-type Record from AttributeCache
 * @phpstan-import-type Marked from AttributeCache
 * @phpstan-import-type Diagnostic from AttributeCache
 */
final class AttributeVisitor extends NodeVisitorAbstract
{
    /** The class a Translatable is written for, lowercased as PHP compares class names */
    private const string TRANSLATABLE = 'pollora\\i18n\\runtime\\translatable';

    /** @var array<int, string> The positional parameters of Translatable's constructor */
    private const array TRANSLATABLE_PARAMETERS = ['text', 'domain', 'context'];

    /** @var list<Record> */
    public array $records = [];

    /** @var list<Marked> */
    public array $marked = [];

    /** @var list<Diagnostic> */
    public array $diagnostics = [];

    private ?string $attribute = null;

    /** The literal textDomain of the attribute; null when it names none */
    private ?string $attributeDomain = null;

    private bool $attributeDomainUnknown = false;

    /** @var array<string, Expr> The declared fields to read when the attribute is left, its names resolved by then */
    private array $declaredFields = [];

    /** @var list<string> The keys of the arrays the visitor is inside, outermost first */
    private array $path = [];

    /** @var list<int> The next implicit key of each array the visitor is inside */
    private array $nextKeys = [];

    /** @var array<string, list<string>> */
    private array $parameterNames = [];

    /**
     * @param  array<class-string, list<string>>  $fields
     */
    public function __construct(private readonly array $fields) {}

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Attribute) {
            $this->enterAttribute($node);

            return null;
        }

        if ($this->attribute === null) {
            return null;
        }

        if ($node instanceof Expr\Array_) {
            $this->nextKeys[] = 0;
        } elseif ($node instanceof Node\ArrayItem) {
            $this->path[] = $this->segment($node);
        } elseif ($this->isTranslatable($node)) {
            $this->collectTranslatable($node);

            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Node\Attribute) {
            foreach ($this->declaredFields as $field => $expression) {
                $this->collect((string) $this->attribute, $field, $expression, $this->attributeDomain);
            }

            $this->declaredFields = [];
            $this->attribute = null;
            $this->attributeDomain = null;
            $this->attributeDomainUnknown = false;
            $this->path = [];
            $this->nextKeys = [];
        } elseif ($this->attribute !== null && $node instanceof Expr\Array_) {
            array_pop($this->nextKeys);
        } elseif ($this->attribute !== null && $node instanceof Node\ArrayItem) {
            array_pop($this->path);
        }

        return null;
    }

    private function enterAttribute(Node\Attribute $node): void
    {
        $class = $node->name->toString();
        $this->attribute = $class;
        $translatable = $this->fields[$class] ?? null;

        if ($translatable === null) {
            $this->attributeDomain = $this->namedTextDomain($node);

            return;
        }

        $values = $this->argumentsByName($node, $class);

        if (isset($values['textDomain']) && ! $this->isNull($values['textDomain'])) {
            $this->attributeDomain = $this->fold($values['textDomain']);

            if ($this->attributeDomain === null) {
                $this->attributeDomainUnknown = true;
                $this->diagnostics[] = [$node->getStartLine(), sprintf('The text domain of #[%s] is not a literal string: its strings were skipped.', $this->shortName($class))];

                return;
            }
        }

        foreach ($translatable as $field) {
            if (isset($values[$field])) {
                $this->declaredFields[$field] = $values[$field];
            }
        }
    }

    /**
     * The textDomain of an attribute that is not declared, which only a named argument can give:
     * its parameters are unknown. A value that is not a literal leaves the domain unknown.
     */
    private function namedTextDomain(Node\Attribute $attribute): ?string
    {
        foreach ($attribute->args as $argument) {
            if ($argument->name?->toString() === 'textDomain' && ! $this->isNull($argument->value)) {
                $domain = $this->fold($argument->value);
                $this->attributeDomainUnknown = $domain === null;

                return $domain;
            }
        }

        return null;
    }

    private function segment(Node\ArrayItem $item): string
    {
        $nextKey = array_pop($this->nextKeys) ?? 0;
        $key = $item->key;

        if (! $key instanceof Expr) {
            $segment = (string) $nextKey;
            $nextKey++;
        } elseif ($key instanceof String_) {
            $segment = $key->value;
        } elseif ($key instanceof Int_) {
            $segment = (string) $key->value;
            $nextKey = max($nextKey, $key->value + 1);
        } else {
            $segment = '?';
        }

        $this->nextKeys[] = $nextKey;

        return $segment;
    }

    private function collectTranslatable(Expr\New_ $new): void
    {
        $values = [];

        /** @var list<Node\Arg> $arguments */
        $arguments = $new->args;

        foreach ($arguments as $position => $argument) {
            $name = $argument->name?->toString() ?? self::TRANSLATABLE_PARAMETERS[$position] ?? null;

            if ($name !== null) {
                $values[$name] = $argument->value;
            }
        }

        $class = $this->shortName((string) $this->attribute);
        $textExpression = $values['text'] ?? null;
        $text = $textExpression === null ? null : $this->fold($textExpression);

        if ($text === null) {
            $this->skipped('text', $class, ($textExpression ?? $new)->getStartLine());

            return;
        }

        if ($text === '') {
            $this->diagnostics[] = [$textExpression->getStartLine(), sprintf('A Translatable in #[%s] has an empty text: it was skipped.', $class)];

            return;
        }

        $domain = $this->optional($values['domain'] ?? null);
        $context = $this->optional($values['context'] ?? null);

        if ($domain === false || $context === false) {
            $this->skipped($domain === false ? 'domain' : 'context', $class, ($domain === false ? $values['domain'] : $values['context'])->getStartLine());

            return;
        }

        if ($domain === null && $this->attributeDomainUnknown) {
            $this->diagnostics[] = [$new->getStartLine(), sprintf('The text domain of #[%s] is not a literal string: a Translatable in it was skipped.', $class)];

            return;
        }

        $this->marked[] = [(string) $this->attribute, implode('.', $this->path), $text, $domain ?? $this->attributeDomain, $context, $textExpression->getStartLine()];
    }

    /**
     * @return string|false|null The literal, false when it is not one, null when absent or null
     */
    private function optional(?Expr $expression): string|false|null
    {
        if (! $expression instanceof Expr || $this->isNull($expression)) {
            return null;
        }

        return $this->fold($expression) ?? false;
    }

    private function skipped(string $argument, string $class, int $line): void
    {
        $this->diagnostics[] = [$line, sprintf('The %s of a Translatable in #[%s] is not a literal string: it was skipped.', $argument, $class)];
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
            if ($this->isTranslatable($item)) {
                continue;
            }

            $text = $this->fold($item);

            if ($text === null) {
                $this->diagnostics[] = [$item->getStartLine(), sprintf('The "%s" argument of #[%s] is not a literal string: it was skipped.', $field, $this->shortName($class))];
            } elseif ($text !== '') {
                $this->records[] = [$class, $field, $text, $domain, $item->getStartLine()];
            }
        }
    }

    /**
     * Whether the expression is `new Translatable(...)`, whatever the way its class was named.
     * What it holds is read where it is met, so a declared field leaves it alone.
     *
     * @phpstan-assert-if-true Expr\New_ $expression
     */
    private function isTranslatable(Node $expression): bool
    {
        return $expression instanceof Expr\New_ && $expression->class instanceof Node\Name && strtolower($expression->class->toString()) === self::TRANSLATABLE;
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
