<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

/**
 * Every project, every version and every document the build resolved, before a page is rendered.
 *
 * The corpus exists because a page cannot be rendered against a partial route table: a link to a
 * document in another project resolves only once that document is registered. Resolving the whole
 * corpus first also means a page that was never published is discovered from the router rather than
 * from a second read of the source.
 *
 * @internal
 */
final readonly class Corpus
{
    /**
     * @param list<ProjectCorpus> $projects Ordered by site path, which is the order the report requires
     */
    public function __construct(
        public array $projects,
    ) {}
}
