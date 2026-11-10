<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Renders the .l10n.php file WordPress 6.5+ loads in place of a .mo file.
 *
 * The output is a literal `return [...]` with no function call, no serialize and
 * no concatenation, so opcache stores the array as an immutable constant and a
 * request only pays a shared-memory read. Plural forms are joined by NUL and
 * keyed by their singular, which is how WP_Translation_File reads them.
 */
final class L10nPhpWriter
{
    /**
     * @param  array<string, string>  $headers  Header name and value; names are written in lower case, as WordPress reads them
     * @param  array<array-key, list<string>>  $messages  Translations by form, keyed by the raw gettext original
     *                                                    ("context\x04msgid", plus "\0msgid_plural" for a plural)
     */
    public function render(array $headers, array $messages): string
    {
        $lines = [];

        foreach ($this->headers($headers) as $name => $value) {
            $lines[] = $this->literal($name).'=>'.$this->literal($value).',';
        }

        $lines[] = "'messages'=>[";

        foreach ($this->messages($messages) as [$original, $translation]) {
            $lines[] = $this->literal($original).'=>'.$this->literal($translation).',';
        }

        $lines[] = '],';

        return "<?php\nreturn [\n".implode("\n", $lines)."\n];\n";
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        $lowered = [];

        foreach ($headers as $name => $value) {
            $name = strtolower($name);

            if ($name !== 'messages') {
                $lowered[$name] = $value;
            }
        }

        uksort($lowered, strcmp(...));

        return $lowered;
    }

    /**
     * @param  array<array-key, list<string>>  $messages
     * @return list<array{string, string}>
     */
    private function messages(array $messages): array
    {
        $entries = [];

        foreach ($messages as $original => $forms) {
            $key = explode("\0", (string) $original, 2)[0];

            if ($key === '' || $forms === [] || in_array('', $forms, true)) {
                continue;
            }

            $entries[$key] = implode("\0", $forms);
        }

        $list = [];

        foreach ($entries as $key => $translation) {
            $list[] = [(string) $key, $translation];
        }

        usort($list, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $list;
    }

    /**
     * A PHP string literal: single-quoted when the text is plain, double-quoted
     * with escapes when it holds control characters, so the file stays printable.
     */
    private function literal(string $text): string
    {
        if (preg_match('/[\x00-\x1f\x7f]/', $text) !== 1) {
            return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $text)."'";
        }

        $escaped = preg_replace_callback(
            '/[\x00-\x1f\x7f\\\\"$]/',
            static fn (array $match): string => match ($match[0]) {
                '\\' => '\\\\',
                '"' => '\\"',
                '$' => '\\$',
                "\n" => '\\n',
                "\t" => '\\t',
                "\r" => '\\r',
                default => sprintf('\\x%02X', ord($match[0])),
            },
            $text,
        );

        return '"'.$escaped.'"';
    }
}
