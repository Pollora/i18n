<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling\Console;

use Pollora\I18n\Tooling\CatalogCompiler;
use Pollora\I18n\Tooling\CompiledFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Compiles the .po files into the .mo and .l10n.php files WordPress loads.
 */
#[AsCommand(name: 'pollora:i18n:compile', description: 'Compile the .po files into .mo and .l10n.php files', aliases: ['compile'])]
final class CompileCommand extends Command
{
    public function __construct(private readonly CatalogCompiler $compiler = new CatalogCompiler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('languages', null, InputOption::VALUE_REQUIRED, 'The folder of the .po files', 'languages')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Only the files of this text domain [default: every .po file]')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'What to produce: mo, php or both separated by a comma', 'mo,php')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Build the files even when they are newer than their .po file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Write nothing and report what would change');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandGuard::run($output, function () use ($input, $output): int {
            $dryRun = Options::flag($input, 'dry-run');
            $report = $this->compiler->compile(
                languages: Options::requiredString($input, 'languages'),
                domain: Options::string($input, 'domain'),
                formats: CompiledFormat::fromList(Options::requiredString($input, 'format')),
                force: Options::flag($input, 'force'),
                dryRun: $dryRun,
            );

            ReportRenderer::render($output, $report, $dryRun);

            return ExitCode::SUCCESS;
        });
    }
}
