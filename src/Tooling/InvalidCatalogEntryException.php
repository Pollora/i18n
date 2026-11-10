<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

final class InvalidCatalogEntryException extends RuntimeException
{
    public static function emptyMessageId(): self
    {
        return new self('A catalogue entry needs a non-empty message id (msgid); the empty one is the header of the file.');
    }

    public static function emptyPlural(): self
    {
        return new self('The plural form (msgid_plural) of a catalogue entry cannot be empty.');
    }
}
