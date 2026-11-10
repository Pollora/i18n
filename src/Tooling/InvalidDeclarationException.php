<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;
use Throwable;

/**
 * A declaration file, or the list of installed packages that points to it, cannot be used.
 */
final class InvalidDeclarationException extends RuntimeException
{
    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('The declaration file "%s" does not exist or cannot be read.', $path));
    }

    public static function fileNotLoadable(string $path, Throwable $previous): self
    {
        return new self(sprintf('The declaration file "%s" failed to load: %s', $path, $previous->getMessage()), 0, $previous);
    }

    public static function notAnArray(string $path): self
    {
        return new self(sprintf('The declaration file "%s" must return an array of the form [Class::class => [\'field\', ...]].', $path));
    }

    public static function invalidClass(string $path, int|string $key): self
    {
        return new self(sprintf('The declaration file "%s" has the key %s, which is not a class name.', $path, var_export($key, true)));
    }

    public static function invalidFields(string $path, string $class): self
    {
        return new self(sprintf('The declaration file "%s" must list the fields of %s as an array of non-empty strings.', $path, $class));
    }

    public static function installedNotReadable(string $path): self
    {
        return new self(sprintf('The list of installed packages "%s" does not exist or cannot be read.', $path));
    }

    public static function installedMalformed(string $path): self
    {
        return new self(sprintf('The list of installed packages "%s" is not valid Composer JSON.', $path));
    }

    public static function invalidPackagePath(string $installedPath, string $package): self
    {
        return new self(sprintf('In "%s", extra.pollora.i18n of %s must be a path relative to the package, without "..".', $installedPath, $package));
    }
}
