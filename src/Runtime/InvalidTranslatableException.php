<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

use RuntimeException;

/**
 * A Translatable was built with a value that cannot be translated.
 */
final class InvalidTranslatableException extends RuntimeException
{
    public static function emptyText(): self
    {
        return new self('A Translatable needs a text: an empty string has nothing to translate.');
    }
}
