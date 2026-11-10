<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Writes a catalogue as the text of a .pot or .po file, always the same text for the same catalogue.
 *
 * The order of the messages in the catalogue does not matter: active messages come first, then the obsolete ones, each group
 * sorted by message id then context (byte order, so no locale changes it). References are sorted and unique, one "#:" line each,
 * and long lines are never wrapped: a string breaks only after each "\n" it holds. The headers keep the order the catalogue gives.
 */
final class PoWriter
{
    private const array ESCAPES = [
        '\\' => '\\\\',
        '"' => '\\"',
        "\x07" => '\\a',
        "\x08" => '\\b',
        "\t" => '\\t',
        "\n" => '\\n',
        "\x0b" => '\\v',
        "\x0c" => '\\f',
        "\r" => '\\r',
    ];

    public function write(Catalog $catalog): string
    {
        $blocks = [];

        if ($catalog->headers !== [] || $catalog->headerComments !== []) {
            $blocks[] = $this->headerBlock($catalog);
        }

        $pluralCount = $catalog->pluralCount();

        foreach ($this->sorted($catalog->messages()) as $message) {
            $blocks[] = $this->messageBlock($message, $pluralCount);
        }

        return implode("\n", $blocks);
    }

    private function headerBlock(Catalog $catalog): string
    {
        $lines = [];

        foreach ($catalog->headerComments as $comment) {
            $lines[] = $this->commentLine('#', $comment);
        }

        $lines[] = 'msgid ""';
        $lines[] = 'msgstr ""';

        foreach ($catalog->headers as $name => $value) {
            $lines[] = '"'.$this->escape($name.': '.$value."\n").'"';
        }

        return implode("\n", $lines)."\n";
    }

    private function messageBlock(CatalogMessage $message, int $pluralCount): string
    {
        $entry = $message->entry;
        $lines = [];

        foreach ($message->translatorComments as $comment) {
            $lines[] = $this->commentLine('#', $comment);
        }

        if (! $message->obsolete) {
            foreach ($entry->comments as $comment) {
                $lines[] = $this->commentLine('#.', $comment);
            }

            foreach ($this->sortedReferences($entry->references) as $reference) {
                $lines[] = '#: '.$reference;
            }
        }

        if ($message->flags !== []) {
            $flags = array_unique($message->flags);
            sort($flags, SORT_STRING);
            $lines[] = '#, '.implode(', ', $flags);
        }

        $prefix = $message->obsolete ? '#~ ' : '';

        if ($entry->msgctxt !== null) {
            $this->appendString($lines, $prefix, 'msgctxt', $entry->msgctxt);
        }

        $this->appendString($lines, $prefix, 'msgid', $entry->msgid);

        if ($entry->plural === null) {
            $this->appendString($lines, $prefix, 'msgstr', $message->translations[0] ?? '');
        } else {
            $this->appendString($lines, $prefix, 'msgid_plural', $entry->plural);

            for ($form = 0, $forms = max(count($message->translations), $pluralCount); $form < $forms; $form++) {
                $this->appendString($lines, $prefix, 'msgstr['.$form.']', $message->translations[$form] ?? '');
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * A comment can hold line breaks in memory; the file cannot, so each line gets its own marker.
     */
    private function commentLine(string $marker, string $comment): string
    {
        $lines = [];

        foreach (preg_split('/\r\n|\r|\n/', $comment) ?: [''] as $line) {
            $lines[] = $line === '' ? $marker : $marker.' '.$line;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendString(array &$lines, string $prefix, string $keyword, string $value): void
    {
        $segments = preg_split('/(?<=\n)/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [''];

        if (count($segments) === 1) {
            $lines[] = $prefix.$keyword.' "'.$this->escape($value).'"';

            return;
        }

        $lines[] = $prefix.$keyword.' ""';

        foreach ($segments as $segment) {
            $lines[] = $prefix.'"'.$this->escape($segment).'"';
        }
    }

    private function escape(string $text): string
    {
        if (preg_match('/[\x00-\x1f"\\\\]/', $text) !== 1) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/[\x00-\x06\x0e-\x1f]/',
            static fn (array $found): string => sprintf('\\%03o', ord($found[0])),
            strtr($text, self::ESCAPES),
        );
    }

    /**
     * @param  list<CatalogMessage>  $messages
     * @return list<CatalogMessage>
     */
    private function sorted(array $messages): array
    {
        usort($messages, static fn (CatalogMessage $a, CatalogMessage $b): int => $a->obsolete <=> $b->obsolete
            ?: strcmp($a->entry->msgid, $b->entry->msgid)
            ?: ($a->entry->msgctxt !== null) <=> ($b->entry->msgctxt !== null)
            ?: strcmp($a->entry->msgctxt ?? '', $b->entry->msgctxt ?? ''));

        return $messages;
    }

    /**
     * @param  list<string>  $references
     * @return list<string>
     */
    private function sortedReferences(array $references): array
    {
        $references = array_unique($references);

        usort($references, static function (string $a, string $b): int {
            [$fileA, $lineA] = self::splitReference($a);
            [$fileB, $lineB] = self::splitReference($b);

            return strcmp($fileA, $fileB) ?: $lineA <=> $lineB ?: strcmp($a, $b);
        });

        return $references;
    }

    /**
     * @return array{string, int}
     */
    private static function splitReference(string $reference): array
    {
        $colon = strrpos($reference, ':');

        if ($colon !== false && preg_match('/^\d+$/', substr($reference, $colon + 1)) === 1) {
            return [substr($reference, 0, $colon), (int) substr($reference, $colon + 1)];
        }

        return [$reference, 0];
    }
}
