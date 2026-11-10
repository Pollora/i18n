<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Puts a catalogue on disk, touching the file only when its content changes: no noise in git, no needless recompilation.
 */
final readonly class PoFileWriter
{
    public function __construct(private PoWriter $writer = new PoWriter) {}

    /**
     * @return bool True when the file was written, false when it already held exactly this content
     */
    public function writeIfChanged(Catalog $catalog, string $path): bool
    {
        $contents = $this->writer->write($catalog);

        if (is_file($path) && filesize($path) === strlen($contents) && file_get_contents($path) === $contents) {
            return false;
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! Quietly::call(static fn (): bool => mkdir($directory, 0777, true)) && ! is_dir($directory)) {
            throw PoFileException::directoryMissing($directory);
        }

        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        if (Quietly::call(static fn (): int|false => file_put_contents($temporary, $contents)) !== strlen($contents)) {
            Quietly::call(static fn (): bool => unlink($temporary));

            throw PoFileException::unwritable($path);
        }

        chmod($temporary, is_file($path) ? (fileperms($path) & 0777) : (0666 & ~umask()));

        if (! Quietly::call(static fn (): bool => rename($temporary, $path))) {
            Quietly::call(static fn (): bool => unlink($temporary));

            throw PoFileException::unwritable($path);
        }

        return true;
    }
}
