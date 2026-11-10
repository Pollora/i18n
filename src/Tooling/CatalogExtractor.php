<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

use Pollora\I18n\Runtime\TranslatableRegistry;

/**
 * Builds the .pot file of a text domain from the translatable attributes of some folders, and merges in templates
 * other tools wrote (the JavaScript and block strings of `wp i18n make-pot`, for example).
 *
 * The template carries no date, its messages are sorted and its references are relative to the base path:
 * the same sources always give the same file.
 */
final readonly class CatalogExtractor
{
    private const string NOTHING_DECLARED = 'No translatable attribute is declared, so no string was extracted: pass --declarations, or run where vendor/composer/installed.json lists the packages that announce them.';

    public function __construct(
        private DeclarationLoader $loader = new DeclarationLoader,
        private FileSink $sink = new FileSink,
        private PoReader $reader = new PoReader,
        private PoWriter $writer = new PoWriter,
        private CatalogMerger $merger = new CatalogMerger,
    ) {}

    /**
     * @param  string  $domain  The text domain; attributes that name another one are left out
     * @param  list<string>  $paths  Folders or files to search for PHP attributes
     * @param  string  $output  Where the .pot file goes
     * @param  string  $basePath  Folder that the references of the .pot are written relative to
     * @param  string|null  $cache  A file that lets the next run skip the files that did not change; none when null
     * @param  list<string>  $merge  Templates to union into the result, such as one from `wp i18n make-pot`
     * @param  string|null  $installed  vendor/composer/installed.json, where the packages announce their declarations; the one under the base path when null and present
     * @param  list<string>  $declarations  Declaration files to load besides the ones the installed packages announce
     * @param  bool  $dryRun  Write nothing, report what would change
     *
     * @throws InvalidRunException
     * @throws InvalidDeclarationException
     * @throws AttributeSourceException
     * @throws PoParseException
     * @throws FileWriteException
     */
    public function extract(
        string $domain,
        array $paths,
        string $output,
        string $basePath,
        ?string $cache = null,
        array $merge = [],
        ?string $installed = null,
        array $declarations = [],
        bool $dryRun = false,
    ): RunReport {
        $this->loadDeclarations($basePath, $installed, $declarations);

        $extraction = (new AttributeSource($dryRun ? null : $cache, $basePath))->extract($paths, $domain);
        $catalog = new Catalog($this->headers($domain), array_map(
            static fn (CatalogEntry $entry): CatalogMessage => new CatalogMessage($entry),
            $extraction->entriesFor($domain),
        ));

        if ($merge !== []) {
            $catalog = $this->merger->mergeAll($catalog, ...array_map(function (string $path): Catalog {
                if (! is_file($path)) {
                    throw InvalidRunException::templateNotFound($path);
                }

                return $this->reader->read($path);
            }, $merge));
        }

        $diagnostics = TranslatableRegistry::all() === [] ? [self::NOTHING_DECLARED, ...$extraction->diagnostics] : $extraction->diagnostics;

        return new RunReport(
            [new FileResult($output, $this->sink->put($output, $this->writer->write($catalog), $dryRun))],
            $diagnostics,
            [sprintf('%d of %d files with a translatable attribute parsed, the others came from the cache.', $extraction->filesParsed, $extraction->filesExamined)],
        );
    }

    /**
     * @param  list<string>  $declarations
     */
    private function loadDeclarations(string $basePath, ?string $installed, array $declarations): void
    {
        $this->loader->loadFiles($declarations);

        if ($installed !== null) {
            $this->loader->loadInstalled($installed);
        } elseif (is_file($basePath.'/vendor/composer/installed.json')) {
            $this->loader->loadInstalled($basePath.'/vendor/composer/installed.json');
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $domain): array
    {
        return [
            'Project-Id-Version' => $domain,
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'Plural-Forms' => PluralForms::ENGLISH,
            'X-Domain' => $domain,
        ];
    }
}
