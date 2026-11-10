<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use RuntimeException;

/**
 * nikic/php-parser, which the package only suggests, is not installed: the attributes cannot be read.
 */
final class PhpParserMissingException extends RuntimeException
{
    public static function notInstalled(): self
    {
        return new self('nikic/php-parser is not installed, and the strings of the attributes cannot be extracted without it. Install it with "composer require --dev nikic/php-parser".');
    }
}
