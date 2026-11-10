<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

final class PoFileException extends RuntimeException
{
    public static function unreadable(string $path): self
    {
        return new self(sprintf('The file "%s" cannot be opened for reading.', $path));
    }

    public static function directoryMissing(string $directory): self
    {
        return new self(sprintf('The directory "%s" does not exist and cannot be created.', $directory));
    }

    public static function unwritable(string $path): self
    {
        return new self(sprintf('The file "%s" cannot be written; the existing file is left untouched.', $path));
    }
}
