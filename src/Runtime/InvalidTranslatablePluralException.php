<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

use RuntimeException;

/**
 * A TranslatablePlural was built with a form that cannot be translated.
 */
final class InvalidTranslatablePluralException extends RuntimeException
{
    public static function emptySingular(): self
    {
        return new self('A TranslatablePlural needs a singular form: an empty string has nothing to translate.');
    }

    public static function emptyPlural(): self
    {
        return new self('A TranslatablePlural needs a plural form: an empty string has nothing to translate.');
    }

    public static function emptyContext(): self
    {
        return new self('A TranslatablePlural context is null for none: an empty string is not a context.');
    }
}
