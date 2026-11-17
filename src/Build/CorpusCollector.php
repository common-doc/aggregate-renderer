<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Exception\MissingDocDirectoryException;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\StrategyFactory;
use InvalidArgumentException;

/**
 * Resolves the corpus: every project, every version and every document, with no rendering and no
 * writes.
 *
 * Only the two exceptions the source seam declares are caught here, and both carry the reason code
 * the report publishes, so the report cannot disagree with what happened. A project that cannot be
 * resolved is recorded and left out, because one unavailable repository does not invalidate the rest
 * of the corpus.
 *
 * @internal
 */
final readonly class CorpusCollector
{
    public function __construct(
        private SourceInterface $source,
        private StrategyFactory $strategies,
    ) {}

    /**
     * @param list<Project> $projects Ordered by site path
     *
     * @throws InvalidArgumentException When a strategy refuses the project it was given
     */
    public function collect(array $projects, Strategy $strategy): Corpus
    {
        $resolved = [];

        foreach ($projects as $project) {
            $resolved[] = $this->project($project, $strategy);
        }

        return new Corpus($resolved);
    }

    /**
     * @throws InvalidArgumentException When the strategy cannot resolve the project
     */
    private function project(Project $project, Strategy $strategy): ProjectCorpus
    {
        try {
            $targets = $this->strategies
                ->create($strategy, $project)
                ->resolve($this->source->candidates($project));
        } catch (SourceUnavailableException $sourceUnavailable) {
            return new ProjectCorpus($project, [], [$sourceUnavailable->reasonCode]);
        }

        if ([] === $targets) {
            return new ProjectCorpus($project, [], [ReasonCode::NoVersion]);
        }

        $corpora = [];
        $reasons = [];

        foreach ($targets as $target) {
            try {
                $documents = $this->source->documents($target);
            } catch (MissingDocDirectoryException|SourceUnavailableException $unavailable) {
                $reasons[] = $unavailable->reasonCode;

                continue;
            }

            $corpora[] = new TargetCorpus($target, $documents);
        }

        return new ProjectCorpus($project, $corpora, $reasons);
    }
}
