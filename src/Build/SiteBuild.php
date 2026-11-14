<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Discovery\ProjectSourceInterface;
use CommonDoc\Aggregate\Exception\DiscoveryException;
use CommonDoc\Aggregate\Exception\MissingCnameException;
use CommonDoc\Aggregate\Output\CnameFile;
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
 * The stages run in a fixed order, and every one of them is a collaborator: the corpus is resolved and
 * every page registered as a route, each project is rendered, the extras and the site address file are
 * written, and the report is written last, so a report never describes a tree that was not produced.
 *
 * The corpus is resolved, and its routes registered, before the first page is rendered. A link can
 * name a document in another project and a sidebar can name any page of the corpus, so both have to
 * exist before any page that could mention them is written. Nothing is written during that phase, and
 * the collaborator that runs it hands back the corpus the render loop reads.
 *
 * A project that fails is recorded and the build continues, because one unavailable repository does
 * not invalidate the rest of the corpus. The exit code follows the totals: a complete corpus exits 0
 * and anything less exits 1.
 */
final readonly class SiteBuild
{
    public function __construct(
        private ProjectSourceInterface $discovery,
        private CorpusRegistrationInterface $corpus,
        private SiteRendererInterface $renderer,
        private ProjectBuild $projectBuild,
        private LinkTotals $links,
    ) {}

    /**
     * @return BuildReport The report that was written, whose exit code is the process exit code
     *
     * @throws InvalidArgumentException When the output tree, the totals or the report path refuse the build
     * @throws JsonException            When the report cannot be encoded
     * @throws MissingCnameException            When the site address file does not hold the configured hostname
     * @throws RuntimeException            When the output tree or the report cannot be written
     * @throws TemplateExceptionInterface  When a page cannot be rendered
     * @throws DiscoveryException          When no project could be discovered at all
     */
    public function build(SiteConfig $config): BuildReport
    {
        $tree = new OutputTree($config->outputDirectory);
        $tree->reset();

        $results  = [];
        $rendered = [];

        $corpus = $this->corpus->resolve($this->projects(), $config->strategy);

        foreach ($corpus->projects as $project) {
            $outcome = $this->projectBuild->render($project);

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
            links   : $this->links->toArray(),
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
