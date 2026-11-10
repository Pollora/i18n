<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * One translatable string of a catalogue, as a .pot or .po file holds it.
 */
final readonly class CatalogEntry
{
    /** gettext joins context and message id with this byte in a .mo file. */
    private const string CONTEXT_SEPARATOR = "\x04";

    /**
     * @param  string  $msgid  The string in its source language
     * @param  string|null  $msgctxt  Disambiguates identical strings with different meanings
     * @param  string|null  $plural  The plural form (msgid_plural), when the string has one
     * @param  list<string>  $comments  Notes for translators, such as the text of a "translators:" comment
     * @param  list<string>  $references  Where the string was found, as "file:line"
     */
    public function __construct(
        public string $msgid,
        public ?string $msgctxt = null,
        public ?string $plural = null,
        public array $comments = [],
        public array $references = [],
    ) {
        if ($msgid === '') {
            throw InvalidCatalogEntryException::emptyMessageId();
        }

        if ($plural === '') {
            throw InvalidCatalogEntryException::emptyPlural();
        }
    }

    /**
     * What identifies the entry in a catalogue: two entries with the same key are the same string.
     */
    public function key(): string
    {
        return $this->msgctxt === null ? $this->msgid : $this->msgctxt.self::CONTEXT_SEPARATOR.$this->msgid;
    }

    public function isPlural(): bool
    {
        return $this->plural !== null;
    }
}
