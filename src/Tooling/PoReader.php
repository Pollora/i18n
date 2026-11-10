<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use Generator;

/**
 * Reads a .pot or .po file into a catalogue, one line at a time.
 *
 * It reads what PoWriter writes without loss, and what msgcat and Poedit write too: CRLF line ends, a byte order mark, strings
 * on several lines, references packed on one line, obsolete entries. Previous-string comments ("#|") are dropped, and so are
 * the flags of the header. A file it cannot read raises a PoParseException that names the line.
 */
final class PoReader
{
    private const string BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    private const array SIMPLE_ESCAPES = [
        'a' => "\x07", 'b' => "\x08", 'f' => "\x0c", 'n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\x0b",
        '\\' => '\\', '"' => '"',
    ];

    public function read(string $path): Catalog
    {
        $stream = Quietly::call(static fn () => fopen($path, 'rb'));

        if ($stream === false) {
            throw PoFileException::unreadable($path);
        }

        try {
            return $this->parse($stream, $path);
        } finally {
            fclose($stream);
        }
    }

    public function readString(string $contents): Catalog
    {
        /** @var resource $stream */
        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            return $this->parse($stream, '(string)');
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function parse($stream, string $source): Catalog
    {
        $headers = [];
        $headerComments = [];
        $headerLine = null;
        $messages = [];
        $firstLines = [];

        foreach ($this->entries($stream, $source) as $buffer) {
            if ($buffer->msgid === null) {
                throw PoParseException::missingMessageId($source, $buffer->startLine);
            }

            if ($buffer->translations === []) {
                throw PoParseException::missingTranslation($source, $buffer->startLine);
            }

            if ($buffer->msgid === '' && $buffer->msgctxt === null && $buffer->plural === null) {
                if ($headerLine !== null) {
                    throw PoParseException::duplicateHeader($source, $buffer->startLine);
                }

                $headerLine = $buffer->startLine;
                $headers = $this->parseHeaders($buffer->translations[0], $source, $buffer->startLine);
                $headerComments = $buffer->translatorComments;

                continue;
            }

            try {
                $message = new CatalogMessage(
                    new CatalogEntry($buffer->msgid, $buffer->msgctxt, $buffer->plural, $buffer->comments, $buffer->references),
                    $buffer->translations,
                    $buffer->flags,
                    $buffer->obsolete,
                    $buffer->translatorComments,
                );
            } catch (InvalidCatalogEntryException $exception) {
                throw PoParseException::invalidEntry($source, $buffer->startLine, $exception);
            }

            $key = $message->key();

            if (isset($firstLines[$key])) {
                throw PoParseException::duplicateMessage($source, $buffer->startLine, $firstLines[$key], $buffer->msgid);
            }

            $firstLines[$key] = $buffer->startLine;
            $messages[] = $message;
        }

        return new Catalog($headers, $messages, $headerComments);
    }

    /**
     * Yields each entry as soon as its last line is read.
     *
     * @param  resource  $stream
     * @return Generator<int, PoEntryBuffer>
     */
    private function entries($stream, string $source): Generator
    {
        $buffer = new PoEntryBuffer;
        $number = 0;

        while (($raw = fgets($stream)) !== false) {
            $number++;
            $line = rtrim($raw, "\r\n");

            if ($number === 1 && str_starts_with($line, self::BYTE_ORDER_MARK)) {
                $line = substr($line, 3);
            }

            if (trim($line) === '') {
                if (! $buffer->isEmpty()) {
                    yield $buffer;
                    $buffer = new PoEntryBuffer;
                }

                continue;
            }

            if ($line[0] === '#' && ! str_starts_with($line, '#~')) {
                if ($buffer->field !== '') {
                    yield $buffer;
                    $buffer = new PoEntryBuffer;
                }

                $buffer->begin($number);
                $this->readComment($buffer, $line, $source, $number);

                continue;
            }

            $obsolete = $line[0] === '#';
            $body = trim($obsolete ? substr($line, 2) : $line);

            if ($body === '' || $body[0] === '|') {
                continue;
            }

            if ($body[0] === '"') {
                if ($buffer->field === '' || $obsolete !== $buffer->obsolete) {
                    throw PoParseException::unexpectedLine($source, $number, $line);
                }

                $buffer->append($this->unquote($body, $source, $number));

                continue;
            }

            if (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr)(?:\[(\d+)\])?[ \t]*(".*")$/s', $body, $found) !== 1) {
                throw PoParseException::unexpectedLine($source, $number, $line);
            }

            if (($found[1] === 'msgctxt' || $found[1] === 'msgid') && $buffer->translations !== []) {
                yield $buffer;
                $buffer = new PoEntryBuffer;
            }

            if ($buffer->field !== '' && $obsolete !== $buffer->obsolete) {
                throw PoParseException::unexpectedLine($source, $number, $line);
            }

            $buffer->begin($number);
            $buffer->obsolete = $obsolete;
            $this->readKeyword($buffer, $found[1], $found[2], $this->unquote($found[3], $source, $number), $source, $number, $line);
        }

        if (! $buffer->isEmpty()) {
            yield $buffer;
        }
    }

    private function readComment(PoEntryBuffer $buffer, string $line, string $source, int $number): void
    {
        $marker = $line[1] ?? '';

        if ($marker === '' || $marker === ' ') {
            $buffer->translatorComments[] = substr($line, 2);

            return;
        }

        $text = substr($line, 2);
        $text = str_starts_with($text, ' ') ? substr($text, 1) : $text;

        match ($marker) {
            '.' => $buffer->comments[] = $text,
            ':' => array_push($buffer->references, ...preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []),
            ',' => array_push($buffer->flags, ...preg_split('/\s*,\s*/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            '|' => null,
            default => throw PoParseException::unexpectedLine($source, $number, $line),
        };
    }

    private function readKeyword(PoEntryBuffer $buffer, string $keyword, string $index, string $value, string $source, int $number, string $line): void
    {
        $valid = match ($keyword) {
            'msgctxt' => $index === '' && $buffer->msgctxt === null && $buffer->msgid === null,
            'msgid' => $index === '' && $buffer->msgid === null,
            'msgid_plural' => $index === '' && $buffer->msgid !== null && $buffer->plural === null && $buffer->translations === [],
            default => $buffer->msgid !== null && $buffer->plural === null
                ? $index === '' && $buffer->translations === []
                : $buffer->plural !== null && $index !== '' && (int) $index === count($buffer->translations),
        };

        if (! $valid) {
            throw PoParseException::unexpectedLine($source, $number, $line);
        }

        switch ($keyword) {
            case 'msgctxt':
                $buffer->msgctxt = $value;
                $buffer->field = 'msgctxt';

                break;
            case 'msgid':
                $buffer->msgid = $value;
                $buffer->field = 'msgid';

                break;
            case 'msgid_plural':
                $buffer->plural = $value;
                $buffer->field = 'plural';

                break;
            default:
                $buffer->translations[] = $value;
                $buffer->field = 'msgstr';
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $text, string $source, int $line): array
    {
        $headers = [];

        foreach (explode("\n", $text) as $headerLine) {
            if ($headerLine === '') {
                continue;
            }

            $colon = strpos($headerLine, ':');

            if ($colon === false) {
                throw PoParseException::malformedHeader($source, $line, $headerLine);
            }

            $value = substr($headerLine, $colon + 1);
            $headers[substr($headerLine, 0, $colon)] = str_starts_with($value, ' ') ? substr($value, 1) : $value;
        }

        return $headers;
    }

    private function unquote(string $quoted, string $source, int $number): string
    {
        if (strlen($quoted) < 2 || $quoted[-1] !== '"') {
            throw PoParseException::malformedString($source, $number);
        }

        $inner = substr($quoted, 1, -1);

        if (preg_match('/["\\\\]/', $inner) !== 1) {
            return $inner;
        }

        if (preg_match('/^(?:[^"\\\\]++|\\\\.)*+$/s', $inner) !== 1) {
            throw PoParseException::malformedString($source, $number);
        }

        return (string) preg_replace_callback(
            '/\\\\(?:([abfnrtv\\\\"])|([0-7]{1,3})|x([0-9A-Fa-f]{1,2})|(.))/s',
            static fn (array $found): string => match (true) {
                $found[1] !== null => self::SIMPLE_ESCAPES[$found[1]],
                $found[2] !== null => chr(octdec($found[2]) & 0xFF),
                $found[3] !== null => chr((int) hexdec($found[3])),
                default => throw PoParseException::unknownEscape($source, $number, (string) $found[4]),
            },
            $inner,
            flags: PREG_UNMATCHED_AS_NULL,
        );
    }
}
