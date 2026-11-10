<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Brings the translated .po files of a text domain up to date with a fresh .pot file, and creates the ones asked for.
 *
 * The files are the ones WordPress looks for: "{domain}-{locale}.po" in one folder. Translations are never lost:
 * what the template no longer has turns obsolete, and a file that is already up to date is not touched.
 */
final readonly class CatalogUpdater
{
    public function __construct(
        private FileSink $sink = new FileSink,
        private PoReader $reader = new PoReader,
        private PoWriter $writer = new PoWriter,
        private CatalogMerger $merger = new CatalogMerger,
        private LanguageFiles $files = new LanguageFiles,
    ) {}

    /**
     * @param  string  $domain  The text domain
     * @param  string  $pot  The fresh template
     * @param  string  $languages  The folder of the .po files
     * @param  list<string>  $locales  Locales to create when they have no file yet, such as "fr_FR"
     * @param  bool  $dryRun  Write nothing, report what would change
     *
     * @throws InvalidRunException
     * @throws PoParseException
     * @throws FileWriteException
     */
    public function update(string $domain, string $pot, string $languages, array $locales = [], bool $dryRun = false): RunReport
    {
        foreach ($locales as $locale) {
            if (preg_match(LanguageFiles::LOCALE_PATTERN, $locale) !== 1) {
                throw InvalidRunException::invalidLocale($locale);
            }
        }

        if (! is_file($pot)) {
            throw InvalidRunException::templateNotFound($pot);
        }

        if ($locales === [] && ! is_dir($languages)) {
            throw InvalidRunException::directoryNotFound($languages);
        }

        $template = $this->reader->read($pot);
        $targets = [];

        foreach ($this->files->poFiles($languages, $domain) as $locale => $path) {
            $targets[$locale] = $path;
        }

        foreach ($locales as $locale) {
            $targets[$locale] ??= $this->files->path($languages, $domain, $locale, '.po');
        }

        ksort($targets, SORT_STRING);
        $results = [];

        foreach ($targets as $locale => $path) {
            $existing = is_file($path) ? $this->reader->read($path) : $this->empty((string) $locale, $template);
            $results[] = new FileResult($path, $this->sink->put($path, $this->writer->write($this->merger->merge($existing, $template)), $dryRun));
        }

        return new RunReport($results);
    }

    private function empty(string $locale, Catalog $template): Catalog
    {
        $headers = $template->headers;
        unset($headers['POT-Creation-Date'], $headers['X-Domain']);
        $plural = PluralForms::forLocale($locale);

        if ($plural === null) {
            unset($headers['Plural-Forms']);
        } else {
            $headers['Plural-Forms'] = $plural;
        }

        $headers['Language'] = $locale;

        return new Catalog($headers);
    }
}
