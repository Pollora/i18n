<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use Countable;

/**
 * A .pot or .po file in memory: the header, then the messages, each unique by its context and message id.
 *
 * Immutable. To add many messages, build the list and call withMessages() once: every with…() call checks the whole catalogue.
 */
final readonly class Catalog implements Countable
{
    private const int DEFAULT_PLURAL_COUNT = 2;

    /** @var array<string, CatalogMessage> */
    private array $byKey;

    /**
     * @param  array<string, string>  $headers  Such as Language, Plural-Forms and Content-Type, in the order the file lists them
     * @param  list<CatalogMessage>  $messages  In insertion order; PoWriter sorts them
     * @param  list<string>  $headerComments  The "# " lines above the header, such as the title and the licence
     */
    public function __construct(
        public array $headers = [],
        array $messages = [],
        public array $headerComments = [],
    ) {
        foreach ($headers as $name => $value) {
            $name = (string) $name;

            if ($name === '' || strpbrk($name.$value, "\r\n") !== false || str_contains($name, ':')) {
                throw InvalidCatalogException::invalidHeader($name);
            }
        }

        $byKey = [];

        foreach ($messages as $message) {
            $key = $message->key();

            if (isset($byKey[$key])) {
                throw InvalidCatalogException::duplicateMessage($message->entry->msgid);
            }

            $byKey[$key] = $message;
        }

        $this->byKey = $byKey;
    }

    /**
     * @return list<CatalogMessage>
     */
    public function messages(): array
    {
        return array_values($this->byKey);
    }

    public function get(string $key): ?CatalogMessage
    {
        return $this->byKey[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->byKey[$key]);
    }

    public function count(): int
    {
        return count($this->byKey);
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * How many plural forms the language has, from "nplurals=" in Plural-Forms; two when the header does not say.
     */
    public function pluralCount(): int
    {
        $forms = $this->headers['Plural-Forms'] ?? '';

        if (preg_match('/nplurals\s*=\s*(\d+)/', $forms, $found) === 1 && (int) $found[1] > 0) {
            return (int) $found[1];
        }

        return self::DEFAULT_PLURAL_COUNT;
    }

    public function withHeader(string $name, string $value): self
    {
        return $this->withHeaders([...$this->headers, $name => $value]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($headers, $this->messages(), $this->headerComments);
    }

    /**
     * @param  list<string>  $headerComments
     */
    public function withHeaderComments(array $headerComments): self
    {
        return new self($this->headers, $this->messages(), $headerComments);
    }

    /**
     * Adds the message, or replaces the one with the same key where it stands.
     */
    public function withMessage(CatalogMessage $message): self
    {
        $byKey = $this->byKey;
        $byKey[$message->key()] = $message;

        return new self($this->headers, array_values($byKey), $this->headerComments);
    }

    /**
     * Replaces all the messages.
     *
     * @param  list<CatalogMessage>  $messages
     */
    public function withMessages(array $messages): self
    {
        return new self($this->headers, $messages, $this->headerComments);
    }

    public function withoutMessage(string $key): self
    {
        $byKey = $this->byKey;
        unset($byKey[$key]);

        return new self($this->headers, array_values($byKey), $this->headerComments);
    }
}
