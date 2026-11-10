<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

final class FileWriteException extends RuntimeException
{
    public static function directoryNotCreated(string $directory): self
    {
        return new self(sprintf('The directory "%s" could not be created.', $directory));
    }

    public static function fileNotWritten(string $path): self
    {
        return new self(sprintf('The file "%s" could not be written.', $path));
    }
}
