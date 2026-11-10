<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use Throwable;

/**
 * What AttributeSource remembers of each file, in a PHP file that returns an array.
 *
 * An entry holds only what the file said, so it stays right when the default domain or
 * the context changes. It is valid while the file keeps its contents and the declared
 * fields stay the same; otherwise the file is read again.
 *
 * @phpstan-type Record array{0: string, 1: string, 2: string, 3: string|null, 4: int}
 * @phpstan-type Diagnostic array{0: int, 1: string}
 * @phpstan-type Entry array{hash: string, records: list<Record>, diagnostics: list<Diagnostic>}
 */
final class AttributeCache
{
    private const int VERSION = 1;

    /** @var array<string, Entry> */
    private array $entries = [];

    /** @var array<string, Entry> */
    private array $kept = [];

    public function __construct(private readonly ?string $path, private readonly string $fieldsVersion)
    {
        if ($path === null || ! is_file($path)) {
            return;
        }

        try {
            $data = (static fn (): mixed => include $path)();
        } catch (Throwable) {
            return;
        }

        if (is_array($data) && ($data['version'] ?? null) === self::VERSION && ($data['fields'] ?? null) === $fieldsVersion && is_array($data['files'] ?? null)) {
            /** @var array<string, Entry> $files */
            $files = $data['files'];
            $this->entries = $files;
        }
    }

    /**
     * @return Entry|null
     */
    public function get(string $file, string $hash): ?array
    {
        $entry = $this->entries[$file] ?? null;

        return is_array($entry) && ($entry['hash'] ?? null) === $hash && is_array($entry['records'] ?? null) && is_array($entry['diagnostics'] ?? null) ? $entry : null;
    }

    /**
     * @param  Entry  $entry
     */
    public function keep(string $file, array $entry): void
    {
        $this->kept[$file] = $entry;
    }

    /**
     * Writes what was kept, and nothing else: files that disappeared leave the cache.
     * The file is left alone when nothing changed.
     */
    public function save(): void
    {
        if ($this->path === null) {
            return;
        }

        ksort($this->kept);
        $contents = '<?php return '.var_export(['version' => self::VERSION, 'fields' => $this->fieldsVersion, 'files' => $this->kept], true).";\n";
        (new AtomicFileWriter)->writeIfChanged($this->path, $contents);
    }
}
