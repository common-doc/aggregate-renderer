<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Version\Target;

/**
 * One published version and the documents it resolved to.
 *
 * The document paths are the ones the source handed over, so the renderer no longer reads the source
 * to find them, and the same list is what the route provider registers.
 *
 * @internal
 */
final readonly class TargetCorpus
{
    /**
     * @param list<non-empty-string> $documents Ordered as the source listed them
     */
    public function __construct(
        public Target $target,
        public array $documents,
    ) {}
}
