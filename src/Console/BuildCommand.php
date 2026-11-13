<?php

declare(strict_types=1);

/**
 * This file is part of the common-doc/aggregate-renderer package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CommonDoc\Aggregate\Console;

use CommonDoc\Aggregate\Build\BuildReport;
use CommonDoc\Aggregate\Build\SiteBuildFactoryInterface;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\Exception\DiscoveryException;
use CommonDoc\Aggregate\Exception\InvalidSiteConfigException;
use CommonDoc\Aggregate\Exception\MissingCnameException;
use CommonDoc\Aggregate\Report\ReportFile;
use CommonDoc\Aggregate\Report\ReportFileFactoryInterface;
use InvalidArgumentException;
use JsonException;
use League\CommonMark\Exception\AlreadyInitializedException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use Override;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * The one command this package publishes.
 *
 * Configuration is read before anything is built, so an unusable `site.json` exits 2 with a diagnostic
 * and no report is written: a report describes a build, and a build that never started has nothing to
 * describe. Discovery is the second thing that can block a run before rendering, for the same reason
 * and with the same exit code: no token and no boundary mean no project was read, so there is nothing
 * to report. Every other outcome is the report's own exit code, which is why the command never decides
 * an exit code of its own.
 */
#[AsCommand(name: self::NAME, description: 'Render the aggregate documentation site.')]
final class BuildCommand extends Command
{
    public const string NAME = 'site:build';

    private const int EXIT_BLOCKED = 2;

    private const string SITE_OPTION_DEFAULT = 'site.json';

    /**
     * @throws ExceptionInterface When the command name is refused
     */
    public function __construct(
        private readonly SiteConfigReader $reader,
        private readonly SiteBuildFactoryInterface $builds,
        private readonly ReportFileFactoryInterface $reports,
    ) {
        parent::__construct(self::NAME);
    }

    /**
     * @throws ExceptionInterface When an option cannot be declared
     */
    #[Override]
    protected function configure(): void
    {
        $this->addOption(
            'site',
            null,
            InputOption::VALUE_REQUIRED,
            'Path to the site configuration file.',
            self::SITE_OPTION_DEFAULT,
        );
        $this->addOption(
            'json',
            null,
            InputOption::VALUE_NONE,
            'Write the report to standard output as JSON.',
        );
    }

    /**
     * @throws ExceptionInterface When an option cannot be read
     * @throws InvalidArgumentException When the report path or the report itself is refused
     * @throws JsonException When the report cannot be encoded as JSON
     * @throws MissingCnameException When the site address file does not hold the configured hostname
     * @throws RuntimeException            When the output tree or the report cannot be written
     * @throws AlreadyInitializedException When the markdown environment has already been configured
     * @throws TemplateExceptionInterface  When a page cannot be rendered
     */
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getOption('site');

        if ('' === $path) {
            $path = self::SITE_OPTION_DEFAULT;
        }

        try {
            $config = $this->reader->read($path);
        } catch (InvalidSiteConfigException $invalid) {
            $this->errorsOf($output)->writeln(sprintf('<error>%s</error>', $invalid->getMessage()));

            return self::EXIT_BLOCKED;
        }

        try {
            $report = $this->builds->create(config: $config)->build(config: $config);
        } catch (DiscoveryException $failed) {
            $this->errorsOf($output)->writeln(sprintf('<error>%s</error>', $failed->getMessage()));

            return self::EXIT_BLOCKED;
        }

        if (true === $input->getOption('json')) {
            $this->writeJson($output, $this->reports->create(config: $config), $report);

            return $report->exitCode;
        }

        $this->writeSummary($output, $config->reportPath, $report);

        return $report->exitCode;
    }

    private function errorsOf(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }

    /**
     * @throws JsonException When the report cannot be encoded
     */
    private function writeJson(OutputInterface $output, ReportFile $file, BuildReport $report): void
    {
        $output->write($file->encode($report), newline: false);
    }

    private function writeSummary(OutputInterface $output, string $reportPath, BuildReport $report): void
    {
        $output->writeln(sprintf(
            'site:build: %d pages in %d projects (%d ok, %d partial, %d failed, %d skipped)',
            $report->totals->pages,
            $report->totals->ok + $report->totals->partial + $report->totals->failed + $report->totals->skipped,
            $report->totals->ok,
            $report->totals->partial,
            $report->totals->failed,
            $report->totals->skipped,
        ));
        $output->writeln(sprintf('Report written to %s', $reportPath));
    }
}
