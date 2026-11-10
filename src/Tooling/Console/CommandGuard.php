<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

use Pollora\I18n\Tooling\InvalidRunException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Turns the exceptions of the tooling into a message on the error output and an exit code.
 */
final class CommandGuard
{
    /**
     * @param  callable(): int  $action
     */
    public static function run(OutputInterface $output, callable $action): int
    {
        try {
            return $action();
        } catch (InvalidRunException $exception) {
            ReportRenderer::error($output, $exception->getMessage());

            return ExitCode::USAGE;
        } catch (RuntimeException $exception) {
            ReportRenderer::error($output, $exception->getMessage());

            return ExitCode::FAILURE;
        }
    }
}
