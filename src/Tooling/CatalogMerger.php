<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Brings catalogues together without losing work, in one pass over each (an index by key, never a nested loop).
 *
 * merge() updates a translated .po file from a freshly extracted .pot; mergeAll() unions several templates into one.
 * Neither reads the clock nor the disk, so the same catalogues always give the same result, and merging a result
 * again with the same template changes nothing.
 */
final class CatalogMerger
{
    private const string FUZZY = 'fuzzy';

    /** Taken from the template whenever it states them. */
    private const string VERSION_HEADER = 'Project-Id-Version';

    /** Taken from the template only when the messages changed, so a re-run on an unchanged template leaves the file alone. */
    private const string CREATION_DATE_HEADER = 'POT-Creation-Date';

    /**
     * Updates an existing catalogue (a .po file) from a fresh template (a .pot file).
     *
     * - New messages are added, untranslated; references and extracted comments follow the template.
     * - Translations, translator comments and flags (fuzzy included) are kept as they are. A message whose plural form
     *   appears or disappears, or whose language has another number of forms, gets as many forms as it needs; none is
     *   emptied except to make room.
     * - Messages the template no longer has become obsolete rather than disappearing; an obsolete message the template
     *   has again comes back with its translation.
     * - The headers of the existing file stay; the template gives the version, and the creation date when messages changed.
     */
    public function merge(Catalog $existing, Catalog $fresh): Catalog
    {
        $pluralCount = $existing->pluralCount();
        $merged = [];

        foreach ($existing->messages() as $message) {
            $template = $fresh->get($message->key());

            $merged[] = ! $template instanceof CatalogMessage || $template->obsolete
                ? $message->withObsolete()
                : $this->update($message, $template, $pluralCount);
        }

        foreach ($fresh->messages() as $template) {
            if (! $template->obsolete && ! $existing->has($template->key())) {
                $merged[] = new CatalogMessage(
                    $template->entry,
                    [],
                    array_values(array_filter($template->flags, static fn (string $flag): bool => $flag !== self::FUZZY)),
                    false,
                    $template->translatorComments,
                );
            }
        }

        return new Catalog(
            $this->headers($existing, $fresh, $merged != $existing->messages()),
            $merged,
            $existing->headerComments,
        );
    }

    /**
     * Unions templates into one, with the base first: a message found in several keeps one entry, whose references and
     * comments are all of theirs (no duplicates), and the translations of the base. The base keeps its headers.
     * Pass the catalogues in the same order every time: comments are listed in that order.
     */
    public function mergeAll(Catalog $base, Catalog ...$others): Catalog
    {
        $merged = [];

        foreach ($base->messages() as $message) {
            $merged[$message->key()] = $message;
        }

        foreach ($others as $other) {
            foreach ($other->messages() as $message) {
                $key = $message->key();

                $merged[$key] = isset($merged[$key]) ? $this->combine($merged[$key], $message) : $message;
            }
        }

        return new Catalog($base->headers, array_values($merged), $base->headerComments);
    }

    private function update(CatalogMessage $message, CatalogMessage $template, int $pluralCount): CatalogMessage
    {
        $flags = $message->flags;

        foreach ($template->flags as $flag) {
            if ($flag !== self::FUZZY && ! in_array($flag, $flags, true)) {
                $flags[] = $flag;
            }
        }

        return new CatalogMessage(
            $template->entry,
            $this->forms($message->translations, $template->isPlural() ? $pluralCount : 1),
            $flags,
            false,
            $message->translatorComments,
        );
    }

    /**
     * @param  list<string>  $translations
     * @return list<string>
     */
    private function forms(array $translations, int $count): array
    {
        if ($translations === [] || count($translations) === $count) {
            return $translations;
        }

        return array_slice(array_pad($translations, $count, ''), 0, $count);
    }

    private function combine(CatalogMessage $base, CatalogMessage $other): CatalogMessage
    {
        $entry = new CatalogEntry(
            $base->entry->msgid,
            $base->entry->msgctxt,
            $base->entry->plural ?? $other->entry->plural,
            $this->union($base->entry->comments, $other->entry->comments),
            $this->union($base->entry->references, $other->entry->references),
        );

        return new CatalogMessage(
            $entry,
            $entry->isPlural() ? $base->translations : array_slice($base->translations, 0, 1),
            $this->union($base->flags, $other->flags),
            $base->obsolete && $other->obsolete,
            $this->union($base->translatorComments, $other->translatorComments),
        );
    }

    /**
     * @param  list<string>  $first
     * @param  list<string>  $second
     * @return list<string>
     */
    private function union(array $first, array $second): array
    {
        return $second === [] ? $first : array_values(array_unique([...$first, ...$second]));
    }

    /**
     * @return array<string, string>
     */
    private function headers(Catalog $existing, Catalog $fresh, bool $changed): array
    {
        $headers = $existing->headers;

        foreach ($changed ? [self::VERSION_HEADER, self::CREATION_DATE_HEADER] : [self::VERSION_HEADER] as $name) {
            $value = $fresh->header($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
