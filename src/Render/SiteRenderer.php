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

namespace CommonDoc\Aggregate\Render;

use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use Override;
use RuntimeException;

/**
 * The renderer the build renders through.
 *
 * It is a composition and nothing else: the pages of one target are one collaborator, the index is
 * another and the not-found page is a third, so each of them can be exercised without the build and
 * without the others. The order the build chose reaches the index unchanged.
 */
final readonly class SiteRenderer implements SiteRendererInterface
{
    public function __construct(
        private TargetRenderer $targets,
        private SiteIndex $index,
        private NotFoundPage $notFound,
    ) {}

    /**
     * @param list<non-empty-string> $documents Ordered by path, as the source returned them
     *
     * @return list<PageResult> Ordered by address, one result per document
     *
     * @throws InvalidArgumentException When a page result is asked for a status its reason code cannot carry
     */
    /**
     * @param list<non-empty-string> $documents Ordered by path, as the source returned them
     *
     * @return list<PageResult> Ordered by address, one result per document
     *
     * @throws InvalidArgumentException When a page result is asked for a status its reason code cannot carry
     */
    #[Override]
    public function render(Target $target, array $documents): array
    {
        return $this->targets->render($target, $documents);
    }

    /**
     * @throws TemplateExceptionInterface When the page cannot be rendered
     * @throws InvalidArgumentException   When the file is not relative to the output directory
     * @throws RuntimeException           When the page cannot be written
     */
    #[Override]
    public function writeNotFoundPage(): void
    {
        $this->notFound->write();
    }

    /**
     * @param list<Target> $targets Ordered by site path, then by version
     *
     * @throws TemplateExceptionInterface When the index cannot be rendered
     * @throws InvalidArgumentException   When the index file is not relative to the output directory
     * @throws RuntimeException           When the index cannot be written
     */
    #[Override]
    public function writeSiteIndex(array $targets): void
    {
        $this->index->write($targets);
    }
}
