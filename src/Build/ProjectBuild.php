<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Exception\MissingDocDirectoryException;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Render\SiteRendererInterface;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\StrategyFactory;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;

use function array_push;

/**
 * One project, from its candidate names to the pages it produced.
 *
 * A project that cannot be resolved is recorded and skipped; a version whose documentation directory is
 * absent is recorded and the other versions still render, because one defective version does not
 * invalidate a project. Only the two exceptions the source seam declares are caught here, and both
 * carry the reason code the report publishes, so the report cannot disagree with what happened.
 *
 * @internal
 */
final readonly class ProjectBuild
{
    public function __construct(
        private SourceInterface $source,
        private SiteRendererInterface $renderer,
        private StrategyFactory $strategies,
    ) {}

    /**
     * @throws InvalidArgumentException When the result cannot describe what was rendered
     */
    public function outcome(Project $project, Strategy $strategy): ProjectOutcome
    {
        try {
            $targets = $this->strategies
                ->create($strategy, $project)
                ->resolve($this->source->candidates($project));
        } catch (MissingDocDirectoryException|SourceUnavailableException $unavailable) {
            return ProjectOutcome::failed($project, $unavailable->reasonCode);
        }

        return $this->render($project, $targets);
    }

    /**
     * @param list<Target> $targets Ordered by version
     *
     * @throws InvalidArgumentException When a result cannot describe what it rendered
     */
    private function render(Project $project, array $targets): ProjectOutcome
    {
        if ([] === $targets) {
            return ProjectOutcome::failed($project, ReasonCode::NoVersions);
        }

        $pages    = [];
        $rendered = [];
        $reasons  = [];

        foreach ($targets as $target) {
            try {
                $documents = $this->source->documents($target);
            } catch (MissingDocDirectoryException|SourceUnavailableException $unavailable) {
                $reasons[] = $unavailable->reasonCode;

                continue;
            }

            array_push($pages, ...$this->renderer->render($target, $documents));

            $rendered[] = $target;
        }

        return ProjectOutcome::for($project, $rendered, $pages, $reasons);
    }
}
