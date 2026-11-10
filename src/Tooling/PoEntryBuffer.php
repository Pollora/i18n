<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * The lines of one entry while PoReader goes through them.
 *
 * @internal
 */
final class PoEntryBuffer
{
    public int $startLine = 0;

    public bool $obsolete = false;

    public ?string $msgctxt = null;

    public ?string $msgid = null;

    public ?string $plural = null;

    /** @var list<string> */
    public array $translations = [];

    /** @var list<string> */
    public array $translatorComments = [];

    /** @var list<string> */
    public array $comments = [];

    /** @var list<string> */
    public array $references = [];

    /** @var list<string> */
    public array $flags = [];

    /** The field the next continuation line belongs to: msgctxt, msgid, plural or msgstr. */
    public string $field = '';

    public function begin(int $line): void
    {
        if ($this->startLine === 0) {
            $this->startLine = $line;
        }
    }

    public function isEmpty(): bool
    {
        return $this->startLine === 0;
    }

    public function append(string $text): void
    {
        match ($this->field) {
            'msgctxt' => $this->msgctxt .= $text,
            'msgid' => $this->msgid .= $text,
            'plural' => $this->plural .= $text,
            default => $this->appendToLastTranslation($text),
        };
    }

    private function appendToLastTranslation(string $text): void
    {
        $last = (string) array_pop($this->translations);
        $this->translations[] = $last.$text;
    }
}
