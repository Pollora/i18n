<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * One message of a .po file: the entry (what is translated), its translations, its flags and whether it is obsolete.
 */
final readonly class CatalogMessage
{
    /**
     * @param  CatalogEntry  $entry  The string, its context, plural, extracted comments and references
     * @param  list<string>  $translations  One per plural form (msgstr[n]); at most one for a singular message; empty when untranslated
     * @param  list<string>  $flags  Such as "fuzzy" or "php-format"
     * @param  bool  $obsolete  The string is no longer in the code (the "#~" lines)
     * @param  list<string>  $translatorComments  The comments translators write, the "# " lines
     */
    public function __construct(
        public CatalogEntry $entry,
        public array $translations = [],
        public array $flags = [],
        public bool $obsolete = false,
        public array $translatorComments = [],
    ) {
        if (! $entry->isPlural() && count($translations) > 1) {
            throw InvalidCatalogMessageException::tooManyTranslations($entry->msgid, count($translations));
        }

        foreach ($flags as $flag) {
            if ($flag === '' || strpbrk($flag, ",\r\n") !== false) {
                throw InvalidCatalogMessageException::invalidFlag($flag);
            }
        }
    }

    public function key(): string
    {
        return $this->entry->key();
    }

    public function isPlural(): bool
    {
        return $this->entry->isPlural();
    }

    public function isFuzzy(): bool
    {
        return in_array('fuzzy', $this->flags, true);
    }

    /**
     * Every form has a translation.
     */
    public function isTranslated(): bool
    {
        return $this->translations !== [] && ! in_array('', $this->translations, true);
    }

    public function withEntry(CatalogEntry $entry): self
    {
        return new self($entry, $this->translations, $this->flags, $this->obsolete, $this->translatorComments);
    }

    /**
     * @param  list<string>  $translations
     */
    public function withTranslations(array $translations): self
    {
        return new self($this->entry, $translations, $this->flags, $this->obsolete, $this->translatorComments);
    }

    /**
     * @param  list<string>  $flags
     */
    public function withFlags(array $flags): self
    {
        return new self($this->entry, $this->translations, $flags, $this->obsolete, $this->translatorComments);
    }

    public function withObsolete(bool $obsolete = true): self
    {
        return new self($this->entry, $this->translations, $this->flags, $obsolete, $this->translatorComments);
    }

    /**
     * @param  list<string>  $translatorComments
     */
    public function withTranslatorComments(array $translatorComments): self
    {
        return new self($this->entry, $this->translations, $this->flags, $this->obsolete, $translatorComments);
    }
}
