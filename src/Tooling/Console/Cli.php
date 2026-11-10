<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

/**
 * The application behind bin/pollora-i18n: extract, update and compile.
 */
final class Cli
{
    public static function application(): Application
    {
        $application = new Application('pollora-i18n');
        $application->setAutoExit(false);
        $application->addCommands([new ExtractCommand, new UpdateCommand, new CompileCommand]);

        return $application;
    }

    /**
     * @return int The exit code, see ExitCode
     */
    public static function run(?InputInterface $input = null): int
    {
        return self::application()->run($input);
    }
}
