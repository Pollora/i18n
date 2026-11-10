<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;
use Throwable;

/**
 * A .po or .pot file that cannot be read, with the line where it went wrong.
 */
final class PoParseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $source,
        public readonly int $lineNumber,
        ?Throwable $previous = null,
    ) {
        parent::__construct(sprintf('%s, line %d: %s', $source, $lineNumber, $message), 0, $previous);
    }

    public static function unexpectedLine(string $source, int $line, string $content): self
    {
        return new self(sprintf('unexpected "%s"', self::excerpt($content)), $source, $line);
    }

    public static function malformedString(string $source, int $line): self
    {
        return new self('the quoted string is not closed or holds an unescaped quote', $source, $line);
    }

    public static function unknownEscape(string $source, int $line, string $character): self
    {
        return new self(sprintf('unknown escape sequence "\\%s"', $character), $source, $line);
    }

    public static function missingMessageId(string $source, int $line): self
    {
        return new self('the entry has comments but no msgid', $source, $line);
    }

    public static function missingTranslation(string $source, int $line): self
    {
        return new self('the entry has a msgid but no msgstr', $source, $line);
    }

    public static function malformedHeader(string $source, int $line, string $headerLine): self
    {
        return new self(sprintf('the header line "%s" has no colon', $headerLine), $source, $line);
    }

    public static function duplicateHeader(string $source, int $line): self
    {
        return new self('the file has a second header (msgid "")', $source, $line);
    }

    public static function duplicateMessage(string $source, int $line, int $firstLine, string $msgid): self
    {
        return new self(sprintf('the message "%s" is already defined at line %d', self::excerpt($msgid), $firstLine), $source, $line);
    }

    public static function invalidEntry(string $source, int $line, Throwable $previous): self
    {
        return new self($previous->getMessage(), $source, $line, $previous);
    }

    private static function excerpt(string $text): string
    {
        $start = preg_match('/^.{0,60}/su', $text, $found) === 1 ? $found[0] : substr($text, 0, 60);

        return $start === $text ? $text : $start.'...';
    }
}
