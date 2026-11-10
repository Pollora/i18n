<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Where the commands put their files: a file is written only when its content changes, and not at all in a dry run.
 */
final readonly class FileSink
{
    public function __construct(private AtomicFileWriter $writer = new AtomicFileWriter) {}

    /**
     * @throws FileWriteException
     */
    public function put(string $path, string $contents, bool $dryRun): FileOutcome
    {
        $existed = is_file($path);

        if ($existed && filesize($path) === strlen($contents) && file_get_contents($path) === $contents) {
            return FileOutcome::Unchanged;
        }

        if (! $dryRun) {
            $this->writer->writeIfChanged($path, $contents);
        }

        return $existed ? FileOutcome::Updated : FileOutcome::Created;
    }
}
