<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * Compiles the .po files of a folder into what WordPress loads: .mo files and .l10n.php files.
 *
 * Only the messages that are translated, not obsolete and not fuzzy are compiled. A file is built when it is missing
 * or older than its .po file, and written only when its bytes change, so a second run touches nothing.
 */
final readonly class CatalogCompiler
{
    public function __construct(
        private FileSink $sink = new FileSink,
        private PoReader $reader = new PoReader,
        private MoCompiler $mo = new MoCompiler,
        private L10nPhpWriter $php = new L10nPhpWriter,
        private LanguageFiles $files = new LanguageFiles,
    ) {}

    /**
     * @param  string  $languages  The folder of the .po files
     * @param  string|null  $domain  Only the files of this text domain; every .po file when null
     * @param  list<CompiledFormat>  $formats  What to produce
     * @param  bool  $force  Build even when the compiled file is newer than its .po file
     * @param  bool  $dryRun  Write nothing, report what would change
     *
     * @throws InvalidRunException
     * @throws PoParseException
     * @throws FileWriteException
     */
    public function compile(string $languages, ?string $domain = null, array $formats = [CompiledFormat::Mo, CompiledFormat::Php], bool $force = false, bool $dryRun = false): RunReport
    {
        if (! is_dir($languages)) {
            throw InvalidRunException::directoryNotFound($languages);
        }

        $results = [];

        foreach ($this->files->poFiles($languages, $domain) as $po) {
            $translations = null;
            $name = substr(basename($po), 0, -3);

            foreach ($formats as $format) {
                $target = rtrim($languages, '/').'/'.$name.$format->extension();

                if (! $force && is_file($target) && filemtime($target) >= filemtime($po)) {
                    $results[] = new FileResult($target, FileOutcome::Unchanged);

                    continue;
                }

                $translations ??= $this->translations($this->reader->read($po));
                $contents = $format === CompiledFormat::Mo
                    ? $this->mo->compile($translations['headers'], $translations['messages'])
                    : $this->php->render($translations['headers'], $translations['messages']);
                $results[] = new FileResult($target, $this->sink->put($target, $contents, $dryRun));
            }
        }

        return new RunReport($results);
    }

    /**
     * @return array{headers: array<string, string>, messages: array<string, list<string>>}
     */
    private function translations(Catalog $catalog): array
    {
        $messages = [];

        foreach ($catalog->messages() as $message) {
            if ($message->obsolete || $message->isFuzzy() || ! $message->isTranslated()) {
                continue;
            }

            $original = $message->key();
            $messages[$message->entry->plural === null ? $original : $original."\0".$message->entry->plural] = $message->translations;
        }

        return ['headers' => $catalog->headers, 'messages' => $messages];
    }
}
