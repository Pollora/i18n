<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

final class AttributeSourceException extends RuntimeException
{
    public static function pathNotFound(string $path): self
    {
        return new self(sprintf('The path "%s" is neither a file nor a directory.', $path));
    }
}
