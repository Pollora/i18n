<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

/**
 * The exit codes of bin/pollora-i18n.
 */
final class ExitCode
{
    /** The command did its work. */
    public const int SUCCESS = 0;

    /** A file could not be read, parsed or written. */
    public const int FAILURE = 1;

    /** An option is missing or invalid; nothing was done. */
    public const int USAGE = 2;

    /** `extract --strict` found strings it could not extract; the template is written all the same. */
    public const int DIAGNOSTICS = 3;
}
