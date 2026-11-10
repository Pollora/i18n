<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Compiles translations to the binary .mo format, byte for byte deterministic.
 *
 * The file has no hash table (hash size 0): gettext, WordPress (MO and
 * WP_Translation_File_MO) and msgunfmt fall back to a binary search of the
 * originals, which are sorted for it. WordPress itself writes its .mo files
 * without a hash table, and a hash only speeds up C readers while making the
 * file larger and the build more complex.
 */
final class MoCompiler
{
    /** Magic number of a little-endian .mo file. */
    private const int MAGIC = 0x950412DE;

    /** Size of the fixed header: magic, revision, count, three offsets, hash size. */
    private const int HEADER_SIZE = 28;

    /**
     * @param  array<string, string>  $headers  Header name and value, such as Content-Type or Plural-Forms
     * @param  array<array-key, list<string>>  $messages  Translations by form, keyed by the raw gettext original:
     *                                                    "context\x04msgid" and, for a plural, "msgid\0msgid_plural"
     */
    public function compile(array $headers, array $messages): string
    {
        $entries = $this->entries($headers, $messages);

        // Byte order, which gettext's binary search relies on.
        usort($entries, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $count = count($entries);
        $originalsOffset = self::HEADER_SIZE;
        $translationsOffset = $originalsOffset + $count * 8;
        $stringsOffset = $translationsOffset + $count * 8;

        $originalsData = '';
        $translationsData = '';

        foreach ($entries as [$original, $translation]) {
            $originalsData .= $original."\0";
            $translationsData .= $translation."\0";
        }

        // The data area holds every original, then every translation, each followed by a NUL.
        $originalsTable = '';
        $translationsTable = '';
        $position = $stringsOffset;

        foreach ($entries as [$original]) {
            $originalsTable .= pack('VV', strlen($original), $position);
            $position += strlen($original) + 1;
        }

        foreach ($entries as [, $translation]) {
            $translationsTable .= pack('VV', strlen($translation), $position);
            $position += strlen($translation) + 1;
        }

        return pack('V*', self::MAGIC, 0, $count, $originalsOffset, $translationsOffset, 0, $stringsOffset)
            .$originalsTable
            .$translationsTable
            .$originalsData
            .$translationsData;
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<array-key, list<string>>  $messages
     * @return list<array{string, string}> Original and joined translations; the header has the empty original
     */
    private function entries(array $headers, array $messages): array
    {
        $entries = [];

        foreach ($messages as $original => $forms) {
            $original = (string) $original;

            if ($original === '' || $forms === [] || in_array('', $forms, true)) {
                continue;
            }

            $entries[] = [$original, implode("\0", $forms)];
        }

        if ($headers !== []) {
            $text = '';

            foreach ($headers as $name => $value) {
                $text .= $name.': '.$value."\n";
            }

            $entries[] = ['', $text];
        }

        return $entries;
    }
}
