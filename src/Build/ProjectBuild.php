<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Render\SiteRendererInterface;
use InvalidArgumentException;

use function array_push;

/**
 * One project, from the corpus it resolved to the pages it produced.
 *
 * Rendering reads nothing but the corpus: the documents arrived with it, so a version the build never
 * resolved cannot be rendered, and the source is read once per version instead of twice. The targets
 * travel beside the result because the result is what the report publishes and the targets are what
 * the site index links.
 *
 * A prose link that named no published document is counted while the pages render, and the project
 * that carried it reports `unresolved-links`. The count is a defect the site owner has to see, and
 * the report is the only place the build can say so.
 *
 * @internal
 */
final readonly class ProjectBuild
{
    public function __construct(
        private SiteRendererInterface $renderer,
        private LinkTotals $links,
    ) {}

    /**
     * @throws InvalidArgumentException When the result cannot describe what was rendered
     */
    public function render(ProjectCorpus $corpus): ProjectOutcome
    {
        $pages      = [];
        $rendered   = [];
        $unresolved = $this->links->count(LinkOutcome::Unresolved);

        foreach ($corpus->targets as $target) {
            array_push($pages, ...$this->renderer->render($target));

            $rendered[] = $target->target;
        }

        $reasons = $corpus->reasons;

        if ($this->links->count(LinkOutcome::Unresolved) > $unresolved) {
            $reasons[] = ReasonCode::UnresolvedLinks;
        }

        return ProjectOutcome::for($corpus->project, $rendered, $pages, $reasons);
    }
}
