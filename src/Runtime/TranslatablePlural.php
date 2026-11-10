<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

use Stringable;

/**
 * A text with a singular and a plural form, marked where it is written inside an argument that is not a plain string.
 *
 * The number it depends on is not known where it is written, so it is not
 * translated by Translator::resolve(): it becomes the structure of WordPress's
 * _n_noop() (or _nx_noop() with a context), and the code that shows the text
 * gives it, with the count, to translate_nooped_plural(). The extraction
 * tooling finds `new TranslatablePlural('...', '...')` in attribute arguments
 * and puts the two forms in the catalogue.
 *
 * Cast to a string it gives the singular form as written, untranslated, like
 * Translatable gives its text: Stringable is also what lets Translator::resolve()
 * tell both apart from a scalar with one check. A parameter typed `string` refuses it.
 */
final readonly class TranslatablePlural implements Stringable
{
    /**
     * @param  string  $singular  The singular form in its source language, never empty
     * @param  string  $plural  The plural form in its source language, never empty
     * @param  string|null  $domain  The text domain, or null for the one of the code that resolves it
     * @param  string|null  $context  The disambiguating context (the `msgctxt` of the catalogue), or null for none, never empty
     * @param  string|null  $comment  A note for the translators (`#.` in the template), ignored when the text is resolved
     *
     * @throws InvalidTranslatablePluralException When the singular form, the plural form or the context is empty
     */
    public function __construct(
        public string $singular,
        public string $plural,
        public ?string $domain = null,
        public ?string $context = null,
        public ?string $comment = null,
    ) {
        if ($singular === '') {
            throw InvalidTranslatablePluralException::emptySingular();
        }

        if ($plural === '') {
            throw InvalidTranslatablePluralException::emptyPlural();
        }

        if ($context === '') {
            throw InvalidTranslatablePluralException::emptyContext();
        }
    }

    public function __toString(): string
    {
        return $this->singular;
    }
}
