<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use FilesystemIterator;
use Generator;
use Pollora\I18n\Runtime\TranslatableRegistry;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Finds the strings of translatable attributes in PHP files.
 *
 * Which attributes, and which of their fields, comes from the TranslatableRegistry. Besides,
 * every `new Translatable('text')` written in the arguments of any attribute is a string,
 * whatever the attribute. A file is read, then parsed only when it can hold one of these, one
 * file at a time. With a cache
 * file, a file that did not change is not parsed again.
 *
 * @phpstan-import-type Record from AttributeCache
 */
final class AttributeSource
{
    /** @var list<string> Directories that hold other people's code or build output */
    private const array SKIPPED_DIRECTORIES = ['vendor', 'node_modules', '.git'];

    /**
     * @param  string|null  $cachePath  A file this source may create to skip unchanged files; never required
     * @param  string|null  $basePath  Directory that references are written relative to; absolute paths when null
     */
    public function __construct(private readonly ?string $cachePath = null, private readonly ?string $basePath = null) {}

    /**
     * @param  list<string>  $paths  Directories (searched for .php files, except vendor, node_modules and .git) or files
     * @param  string  $defaultDomain  The text domain of an attribute that does not name one with textDomain
     * @param  string|null  $context  The msgctxt given to every entry, when the strings need one
     *
     * @throws AttributeSourceException When a path does not exist
     * @throws FileWriteException When the cache cannot be written
     */
    public function extract(array $paths, string $defaultDomain, ?string $context = null): AttributeExtraction
    {
        $fields = TranslatableRegistry::all();
        $cache = new AttributeCache($this->cachePath, $this->fieldsVersion($fields));
        $scanner = new AttributeFileScanner;
        $needles = $this->needles($fields);
        $strings = [];
        $diagnostics = [];
        $examined = 0;
        $parsed = 0;

        foreach ($this->files($paths) as $file) {
            $contents = @file_get_contents($file);

            if ($contents === false) {
                $diagnostics[] = $this->reference($file, 1).': The file could not be read.';

                continue;
            }

            if (! $this->canHoldTranslatableAttribute($contents, $needles)) {
                continue;
            }

            $hash = md5($contents);
            $entry = $cache->get($file, $hash);
            $examined++;

            if ($entry === null) {
                $parsed++;
                $entry = ['hash' => $hash] + $scanner->scan($contents, $fields);
            }

            $cache->keep($file, $entry);

            foreach ($entry['diagnostics'] as [$line, $message]) {
                $diagnostics[] = $this->reference($file, $line).': '.$message;
            }

            foreach ($entry['records'] as [$class, $field, $msgid, $domain, $line]) {
                $key = $context === null ? $msgid : $context."\x04".$msgid;
                $domain ??= $defaultDomain;
                $strings[$domain][$key] ??= ['msgid' => $msgid, 'context' => $context, 'comments' => [], 'references' => []];
                $strings[$domain][$key]['comments'][] = 'Attribute '.$this->shortName($class).'::'.$field;
                $strings[$domain][$key]['references'][] = $this->reference($file, $line);
            }

            foreach ($entry['marked'] as [$class, $path, $msgid, $domain, $own, $line]) {
                $ownContext = $own ?? $context;
                $key = $ownContext === null ? $msgid : $ownContext."\x04".$msgid;
                $domain ??= $defaultDomain;
                $strings[$domain][$key] ??= ['msgid' => $msgid, 'context' => $ownContext, 'comments' => [], 'references' => []];
                $strings[$domain][$key]['comments'][] = 'Attribute '.$this->shortName($class).($path === '' ? '' : ' › '.$path);
                $strings[$domain][$key]['references'][] = $this->reference($file, $line);
            }
        }

        $cache->save();
        sort($diagnostics, SORT_NATURAL);

        return new AttributeExtraction($this->entries($strings), $diagnostics, $examined, $parsed);
    }

    /**
     * @param  array<string, array<string, array{msgid: string, context: string|null, comments: list<string>, references: list<string>}>>  $strings
     * @return array<string, list<CatalogEntry>>
     */
    private function entries(array $strings): array
    {
        ksort($strings);
        $entries = [];

        foreach ($strings as $domain => $messages) {
            ksort($messages);

            foreach ($messages as $message) {
                $comments = array_values(array_unique($message['comments']));
                $references = array_values(array_unique($message['references']));
                sort($comments);
                sort($references, SORT_NATURAL);
                $entries[$domain][] = new CatalogEntry($message['msgid'], $message['context'], null, $comments, $references);
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $paths
     * @return Generator<int, string>
     */
    private function files(array $paths): Generator
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                yield $path;

                continue;
            }

            if (! is_dir($path)) {
                throw AttributeSourceException::pathNotFound($path);
            }

            $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $filtered = new RecursiveCallbackFilterIterator($directory, static fn (SplFileInfo $item): bool => $item->isDir()
                ? ! in_array($item->getFilename(), self::SKIPPED_DIRECTORIES, true)
                : $item->getExtension() === 'php' && ! str_ends_with($item->getFilename(), '.blade.php'));

            /** @var SplFileInfo $item */
            foreach (new RecursiveIteratorIterator($filtered) as $item) {
                yield $item->getPathname();
            }
        }
    }

    /**
     * The short names of the declared classes: a file that names none of them cannot use one of them.
     *
     * @param  array<class-string, list<string>>  $fields
     * @return list<string>
     */
    private function needles(array $fields): array
    {
        return array_values(array_unique(array_map($this->shortName(...), array_keys($fields))));
    }

    /**
     * The cheap test that decides whether a file is parsed. It must never refuse a file that
     * holds a string, so it looks for what such a file cannot do without: an attribute (`#[`),
     * and either the short name of a declared class or the word Translatable. That word is in
     * every spelling of the class, an alias included, since an alias is declared by naming it
     * (`use ...\Translatable as T`); PHP compares class names without regard to case.
     *
     * @param  list<string>  $needles
     */
    private function canHoldTranslatableAttribute(string $contents, array $needles): bool
    {
        return str_contains($contents, '#[') && ($this->mentionsAny($contents, $needles) || preg_match('/\btranslatable\b/i', $contents) === 1);
    }

    /**
     * @param  list<string>  $needles
     */
    private function mentionsAny(string $contents, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($contents, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<class-string, list<string>>  $fields
     */
    private function fieldsVersion(array $fields): string
    {
        ksort($fields);

        foreach ($fields as &$names) {
            sort($names);
        }

        return md5(serialize($fields));
    }

    private function reference(string $file, int $line): string
    {
        $base = $this->basePath === null ? null : rtrim($this->basePath, '/').'/';

        return ($base !== null && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file).':'.$line;
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
