<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

use Pollora\I18n\Tooling\CatalogExtractor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Builds the .pot file of a text domain from the translatable attributes of some folders.
 */
#[AsCommand(name: 'pollora:i18n:extract', description: 'Extract the strings of translatable attributes into a .pot file', aliases: ['extract'])]
final class ExtractCommand extends Command
{
    public function __construct(private readonly CatalogExtractor $extractor = new CatalogExtractor, private readonly ?string $basePath = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'The text domain')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A folder or file to search; repeat it for several')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'The .pot file to write [default: languages/{domain}.pot]')
            ->addOption('cache', null, InputOption::VALUE_REQUIRED, 'A file that lets the next run skip the files that did not change')
            ->addOption('merge', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A .pot file to union into the result, such as one from `wp i18n make-pot`')
            ->addOption('installed', null, InputOption::VALUE_REQUIRED, 'vendor/composer/installed.json, where packages announce their translatable attributes [default: the one of the current folder, when present]')
            ->addOption('declarations', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A declaration file returning [Class::class => [\'field\']]; repeat it for several')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with code 3 when a string could not be extracted')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Write nothing and report what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandGuard::run($output, function () use ($input, $output): int {
            $domain = Options::requiredString($input, 'domain');
            $dryRun = Options::flag($input, 'dry-run');
            $report = $this->extractor->extract(
                domain: $domain,
                paths: Options::requiredStrings($input, 'path'),
                output: Options::string($input, 'output') ?? 'languages/'.$domain.'.pot',
                basePath: $this->basePath ?? (getcwd() ?: '.'),
                cache: Options::string($input, 'cache'),
                merge: Options::strings($input, 'merge'),
                installed: Options::string($input, 'installed'),
                declarations: Options::strings($input, 'declarations'),
                dryRun: $dryRun,
            );

            ReportRenderer::render($output, $report, $dryRun);

            return Options::flag($input, 'strict') && $report->diagnostics !== [] ? ExitCode::DIAGNOSTICS : ExitCode::SUCCESS;
        });
    }
}
