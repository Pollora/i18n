<?php

declare(strict_types=1);

/*
 * Runs AttributeSource::extract() in a process where nikic/php-parser is missing (see without-php-parser.php).
 * Prints the exception class and message, and whether a PhpParser class was loaded.
 */

use Pollora\I18n\Tooling\AttributeSource;
use Pollora\I18n\Tooling\PhpParserMissingException;

try {
    (new AttributeSource)->extract([__DIR__], 'books');
    $caught = null;
} catch (PhpParserMissingException $exception) {
    $caught = [$exception::class, $exception->getMessage(), $exception instanceof RuntimeException];
}

echo json_encode(['caught' => $caught, 'parserLoaded' => class_exists('PhpParser\ParserFactory', false)], JSON_THROW_ON_ERROR);
