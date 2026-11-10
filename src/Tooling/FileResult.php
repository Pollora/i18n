<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * One file a command looked at and what it did to it.
 */
final readonly class FileResult
{
    public function __construct(public string $path, public FileOutcome $outcome) {}
}
