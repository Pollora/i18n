<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * What AttributeSource found: the strings by text domain, what it had to leave out, and how much work it did.
 */
final readonly class AttributeExtraction
{
    /**
     * @param  array<string, list<CatalogEntry>>  $entries  The entries of each text domain, in a stable order
     * @param  list<string>  $diagnostics  What was left out and why, one line each, in a stable order
     * @param  int  $filesExamined  Files that contain an attribute of a declared class
     * @param  int  $filesParsed  Files that had to be parsed (the others came from the cache)
     */
    public function __construct(
        public array $entries,
        public array $diagnostics,
        public int $filesExamined,
        public int $filesParsed,
    ) {}

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        return array_map(strval(...), array_keys($this->entries));
    }

    /**
     * @return list<CatalogEntry>
     */
    public function entriesFor(string $domain): array
    {
        return $this->entries[$domain] ?? [];
    }
}
