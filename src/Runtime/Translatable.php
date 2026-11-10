<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

use Stringable;

/**
 * A text a person reads, marked where it is written inside an argument that is not a plain string.
 *
 * It is a value and does nothing by itself: casting it to a string gives the
 * text as written, without translating it and without reading anything. The
 * translation happens once, when Translator::resolve() walks the arguments.
 * The extraction tooling finds `new Translatable('...')` in attribute arguments
 * and puts the text in the catalogue.
 *
 * A parameter typed `string` refuses the object: use it where the parameter is
 * an array, `mixed` or `string|Stringable`.
 */
final readonly class Translatable implements Stringable
{
    /**
     * @param  string  $text  The text in its source language, never empty
     * @param  string|null  $domain  The text domain, or null for the one of the code that resolves it
     * @param  string|null  $context  The disambiguating context (the `msgctxt` of the catalogue), or null for none
     *
     * @throws InvalidTranslatableException When the text is empty
     */
    public function __construct(
        public string $text,
        public ?string $domain = null,
        public ?string $context = null,
    ) {
        if ($text === '') {
            throw InvalidTranslatableException::emptyText();
        }
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
