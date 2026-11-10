<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * What a command did to a file, or would have done in a dry run.
 */
enum FileOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
}
