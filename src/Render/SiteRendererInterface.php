<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Render;

use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Version\Target;

/**
 * The seam the build renders through.
 *
 * `common-doc/commondoc` owns document rendering, and its rendering seam is not published yet. Until
 * it is, nothing in this package reaches into `CommonDoc\Render`, so this interface is the whole of
 * the integration surface: the build hands a target and its documents over, and receives the page
 * results it then reports. One class implements this interface, and that class is the only place the
 * dependency is touched.
 *
 * @internal
 */
interface SiteRendererInterface
{
    /**
     * Renders every document of one target into the output tree.
     *
     * A page that cannot be rendered is reported rather than thrown, so one defective document never
     * takes the rest of the corpus with it.
     *
     * @param list<non-empty-string> $documents Ordered by path, as {@see \CommonDoc\Aggregate\Source\SourceInterface::documents()} returned them
     *
     * @return list<PageResult> Ordered by address, one result per document
     */
    public function render(Target $target, array $documents): array;

    /**
     * Writes the page served for an address that resolves to nothing.
     */
    public function writeNotFoundPage(): void;

    /**
     * Writes the site index that links the published versions.
     *
     * @param list<Target> $targets Ordered by site path, then by version
     */
    public function writeSiteIndex(array $targets): void;
}
