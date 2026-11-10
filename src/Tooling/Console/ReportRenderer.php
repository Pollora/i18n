<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

use Pollora\I18n\Tooling\FileOutcome;
use Pollora\I18n\Tooling\RunReport;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints a report the same way every time: one line per file, then one summary line.
 *
 *     created   languages/books.pot
 *     unchanged languages/books-fr_FR.po
 *     1 created, 0 updated, 1 unchanged
 *
 * Diagnostics and errors go to the error output, so a pipe of the file list stays clean.
 */
final class ReportRenderer
{
    public static function render(OutputInterface $output, RunReport $report, bool $dryRun): void
    {
        foreach ($report->files as $file) {
            $output->writeln(sprintf('%-9s %s', $file->outcome->value, $file->path), OutputInterface::OUTPUT_RAW);
        }

        $output->writeln(
            sprintf(
                '%d created, %d updated, %d unchanged%s',
                $report->count(FileOutcome::Created),
                $report->count(FileOutcome::Updated),
                $report->count(FileOutcome::Unchanged),
                $dryRun ? ' (dry run: nothing was written)' : '',
            ),
            OutputInterface::OUTPUT_RAW,
        );

        foreach ($report->notes as $note) {
            $output->writeln($note, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_VERBOSE);
        }

        $errors = self::errorOutput($output);

        foreach ($report->diagnostics as $diagnostic) {
            $errors->writeln('warning: '.$diagnostic, OutputInterface::OUTPUT_RAW);
        }
    }

    public static function error(OutputInterface $output, string $message): void
    {
        self::errorOutput($output)->writeln('error: '.$message, OutputInterface::OUTPUT_RAW);
    }

    private static function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
