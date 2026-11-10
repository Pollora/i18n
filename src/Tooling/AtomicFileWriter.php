<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Writes a file so that a reader never sees it half written, and not at all when nothing changed.
 */
final class AtomicFileWriter
{
    /**
     * Writes the contents to a temporary file next to the target, then renames it over the target.
     * The directory is created when missing. A file that already holds these exact contents is left alone,
     * so its modification time stays and a build tool does not see a change.
     *
     * @return bool True when the file was written, false when it already held the contents
     *
     * @throws FileWriteException
     */
    public function writeIfChanged(string $path, string $contents): bool
    {
        if (is_file($path) && filesize($path) === strlen($contents) && file_get_contents($path) === $contents) {
            return false;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw FileWriteException::directoryNotCreated($directory);
        }

        // A name of its own in the target directory, so that the rename never crosses a filesystem.
        $temporary = $directory.'/.i18n-'.bin2hex(random_bytes(6)).'.tmp';

        if (@file_put_contents($temporary, $contents) !== strlen($contents) || ! @rename($temporary, $path)) {
            @unlink($temporary);

            throw FileWriteException::fileNotWritten($path);
        }

        return true;
    }
}
