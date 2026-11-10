<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

final class InvalidCatalogMessageException extends RuntimeException
{
    public static function tooManyTranslations(string $msgid, int $count): self
    {
        return new self(sprintf('The message "%s" has no plural form but %d translations; a singular message has one.', $msgid, $count));
    }

    public static function invalidFlag(string $flag): self
    {
        return new self(sprintf('"%s" is not a valid flag: a flag is not empty and holds no comma and no line break.', $flag));
    }
}
