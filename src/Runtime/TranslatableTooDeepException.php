<?php

declare(strict_types=1);

namespace Pollora\I18n\Runtime;

use RuntimeException;

/**
 * An array holding Translatable texts is nested deeper than Translator::resolve() accepts.
 */
final class TranslatableTooDeepException extends RuntimeException
{
    public static function exceeds(int $maximum): self
    {
        return new self(sprintf('The arguments are nested more than %d levels deep: a circular reference, or an array that is not an argument list.', $maximum));
    }
}
