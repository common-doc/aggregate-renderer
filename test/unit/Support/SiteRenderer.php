<?php

/*
 * This file is part of the common-doc/aggregate-renderer package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace CommonDocTest\Aggregate\Support;

use Closure;
use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Render\SiteRendererInterface;
use CommonDoc\Aggregate\Version\Target;

use function sprintf;

/**
 * A renderer double that records what the build asked of it.
 *
 * The page policy is a closure so a test can script a page failure without a second double, and the
 * default policy renders one page per document, which is what a working renderer produces.
 */
final class SiteRenderer implements SiteRendererInterface
{
    /** @var list<array{Target, list<non-empty-string>}> */
    public array $rendered = [];

    /** @var list<Target> */
    public array $indexed = [];

    public int $notFoundPages = 0;

    /**
     * @var Closure(Target, list<non-empty-string>): list<PageResult>
     */
    private readonly Closure $pages;

    /**
     * @param (Closure(Target, list<non-empty-string>): list<PageResult>)|null $pages
     */
    public function __construct(?Closure $pages = null)
    {
        $this->pages = $pages ?? self::onePagePerDocument(...);
    }

    /**
     * @param list<non-empty-string> $documents
     *
     * @return list<PageResult>
     */
    private static function onePagePerDocument(Target $target, array $documents): array
    {
        $pages = [];

        foreach ($documents as $document) {
            $pages[] = new PageResult(
                address   : sprintf(
                    'https://docs.example.com/%s/%s/%s',
                    $target->project->sitePath,
                    $target->version,
                    $document,
                ),
                status    : PageStatus::Rendered,
                reasonCode: null,
                fromCache : false,
            );
        }

        return $pages;
    }

    /**
     * @param list<non-empty-string> $documents
     *
     * @return list<PageResult>
     */
    public function render(Target $target, array $documents): array
    {
        $this->rendered[] = [$target, $documents];

        return ($this->pages)($target, $documents);
    }

    public function writeNotFoundPage(): void
    {
        $this->notFoundPages++;
    }

    /**
     * @param list<Target> $targets
     */
    public function writeSiteIndex(array $targets): void
    {
        $this->indexed = $targets;
    }
}
