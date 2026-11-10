<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

final class InvalidCatalogException extends RuntimeException
{
    public static function duplicateMessage(string $msgid): self
    {
        return new self(sprintf('The catalogue holds the message "%s" twice; a message is unique by its context and its message id.', $msgid));
    }

    public static function invalidHeader(string $name): self
    {
        return new self(sprintf('"%s" is not a valid header: a name is not empty and holds no colon, and neither name nor value holds a line break.', $name));
    }
}
