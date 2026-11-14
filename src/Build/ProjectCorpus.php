<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Discovery\Project;

/**
 * One project as it resolved: the versions carrying documents, and the reasons the others did not.
 *
 * A version whose documentation directory is absent is recorded in `reasons` and left out of
 * `targets`, because one defective version does not invalidate a project. An empty `reasons` is what
 * a clean resolution looks like, which is what the outcome turns into a complete status.
 *
 * @internal
 */
final readonly class ProjectCorpus
{
    /**
     * @param list<TargetCorpus> $targets Ordered by version
     * @param list<ReasonCode>   $reasons Empty when the project resolved cleanly
     */
    public function __construct(
        public Project $project,
        public array $targets,
        public array $reasons,
    ) {}
}
