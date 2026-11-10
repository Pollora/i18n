<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

/**
 * A command was asked something it cannot do: a missing option, an unknown format, a file or folder that is not there.
 */
final class InvalidRunException extends RuntimeException
{
    public static function missingOption(string $name): self
    {
        return new self(sprintf('The option "--%s" is required.', $name));
    }

    public static function unknownFormat(string $format): self
    {
        return new self(sprintf('The format "%s" is unknown; use "mo", "php" or both separated by a comma.', $format));
    }

    public static function invalidLocale(string $locale): self
    {
        return new self(sprintf('The locale "%s" is not a WordPress locale such as "fr_FR".', $locale));
    }

    public static function templateNotFound(string $path): self
    {
        return new self(sprintf('The template "%s" does not exist.', $path));
    }

    public static function directoryNotFound(string $path): self
    {
        return new self(sprintf('The directory "%s" does not exist.', $path));
    }
}
