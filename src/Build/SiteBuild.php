<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Discovery\ProjectSourceInterface;
use CommonDoc\Aggregate\Output\CnameFile;
use CommonDoc\Aggregate\Output\MissingCname;
use CommonDoc\Aggregate\Output\OutputTree;
use CommonDoc\Aggregate\Render\SiteRendererInterface;
use CommonDoc\Aggregate\Report\ReportFile;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;
use JsonException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use RuntimeException;

use function array_push;
use function strcmp;
use function usort;

/**
 * One build, from a configuration to a report file.
 *
 * The stages run in a fixed order, and every one of them is a collaborator: the corpus is discovered,
 * each project is built, the extras and the site address file are written, and the report is written
 * last, so a report never describes a tree that was not produced.
 *
 * A project that fails is recorded and the build continues, because one unavailable repository does
 * not invalidate the rest of the corpus. The exit code follows the totals: a complete corpus exits 0
 * and anything less exits 1.
 */
final readonly class SiteBuild
{
    public function __construct(
        private ProjectSourceInterface $discovery,
        private SiteRendererInterface $renderer,
        private ProjectBuild $projectBuild,
    ) {}

    /**
     * @return BuildReport The report that was written, whose exit code is the process exit code
     *
     * @throws InvalidArgumentException When the output tree, the totals or the report path refuse the build
     * @throws JsonException            When the report cannot be encoded
     * @throws MissingCname            When the site address file does not hold the configured hostname
     * @throws RuntimeException            When the output tree or the report cannot be written
     * @throws TemplateExceptionInterface  When a page cannot be rendered
     */
    public function build(SiteConfig $config): BuildReport
    {
        $tree = new OutputTree($config->outputDirectory);
        $tree->reset();

        $results  = [];
        $rendered = [];

        foreach ($this->projects() as $project) {
            $outcome = $this->projectBuild->outcome($project, $config->strategy);

            $results[] = $outcome->result;

            array_push($rendered, ...$outcome->targets);
        }

        usort($rendered, Target::compare(...));

        $this->renderer->writeSiteIndex($rendered);
        $this->renderer->writeNotFoundPage();

        $cname = new CnameFile($tree, $config->hostname);
        $cname->write();
        $cname->verify();

        $totals = Total::of($results);
        $report = new BuildReport(
            exitCode: 0 === ($totals->partial + $totals->failed) ? 0 : 1,
            projects: $results,
            totals  : $totals,
        );

        new ReportFile($config->reportPath)->write($report);

        return $report;
    }

    /**
     * @return list<Project> Ordered by site path, which is the order the report requires
     */
    private function projects(): array
    {
        $projects = $this->discovery->all();

        usort($projects, static fn(Project $left, Project $right): int => strcmp(
            $left->sitePath,
            $right->sitePath,
        ));

        return $projects;
    }
}
