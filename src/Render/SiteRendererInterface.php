<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Render;

use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use RuntimeException;

/**
 * The seam the build renders through.
 *
 * This package renders documents and `common-doc/commondoc` supplies the document model and the page
 * context, but the build never sees either of them: it hands a target and its document paths over,
 * and receives the page results it then reports. One class implements this interface, and that class
 * is the only place the rendering dependency is touched, so the integration surface stays a single
 * class rather than a scatter of calls.
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
     *
     * @throws InvalidArgumentException When a page result is asked for a status its reason code cannot carry
     */
    public function render(Target $target, array $documents): array;

    /**
     * Writes the page served for an address that resolves to nothing.
     *
     * @throws TemplateExceptionInterface When the page cannot be rendered
     * @throws InvalidArgumentException   When the file is not relative to the output directory
     * @throws RuntimeException           When the page cannot be written
     */
    public function writeNotFoundPage(): void;

    /**
     * Writes the site index that links the published versions.
     *
     * @param list<Target> $targets Ordered by site path, then by version
     *
     * @throws TemplateExceptionInterface When the index cannot be rendered
     * @throws InvalidArgumentException   When the index file is not relative to the output directory
     * @throws RuntimeException           When the index cannot be written
     */
    public function writeSiteIndex(array $targets): void;
}
