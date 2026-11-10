<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

use Pollora\I18n\Tooling\CatalogUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Updates the translated .po files of a text domain from a fresh .pot file.
 */
#[AsCommand(name: 'pollora:i18n:update', description: 'Merge a .pot file into the {domain}-{locale}.po files, keeping their translations', aliases: ['update'])]
final class UpdateCommand extends Command
{
    public function __construct(private readonly CatalogUpdater $updater = new CatalogUpdater)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'The text domain')
            ->addOption('pot', null, InputOption::VALUE_REQUIRED, 'The .pot file to merge [default: {languages}/{domain}.pot]')
            ->addOption('languages', null, InputOption::VALUE_REQUIRED, 'The folder of the .po files', 'languages')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A locale to create when it has no file yet, such as fr_FR; repeat it for several')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Write nothing and report what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandGuard::run($output, function () use ($input, $output): int {
            $domain = Options::requiredString($input, 'domain');
            $languages = Options::requiredString($input, 'languages');
            $dryRun = Options::flag($input, 'dry-run');
            $report = $this->updater->update(
                domain: $domain,
                pot: Options::string($input, 'pot') ?? rtrim($languages, '/').'/'.$domain.'.pot',
                languages: $languages,
                locales: Options::strings($input, 'locale'),
                dryRun: $dryRun,
            );

            ReportRenderer::render($output, $report, $dryRun);

            return ExitCode::SUCCESS;
        });
    }
}
