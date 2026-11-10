<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * What a command did: the files in a stable order, the lines it had to leave out, and notes for a verbose run.
 */
final readonly class RunReport
{
    /**
     * @param  list<FileResult>  $files
     * @param  list<string>  $diagnostics  What was left out and why, one line each
     * @param  list<string>  $notes  Figures for a verbose run, such as how many files were parsed
     */
    public function __construct(public array $files, public array $diagnostics = [], public array $notes = []) {}

    public function count(FileOutcome $outcome): int
    {
        return count(array_filter($this->files, static fn (FileResult $file): bool => $file->outcome === $outcome));
    }

    /**
     * Files that were, or in a dry run would have been, created or updated.
     */
    public function written(): int
    {
        return count($this->files) - $this->count(FileOutcome::Unchanged);
    }
}
