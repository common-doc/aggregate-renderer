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

namespace CommonDocTest\Aggregate\Build;

use CommonDoc\Aggregate\Build\LinkOutcome;
use CommonDoc\Aggregate\Build\LinkTotals;
use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Build\ProjectBuild;
use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Build\TargetCorpus;
use CommonDocTest\Aggregate\Support\SiteRenderer;
use CommonDocTest\Aggregate\Support\Targets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectBuild::class)]
#[CoversClass(LinkTotals::class)]
#[CoversMethod(ProjectBuild::class, 'render')]
#[CoversMethod(LinkTotals::class, 'add')]
#[CoversMethod(LinkTotals::class, 'count')]
final class ProjectBuildTest extends TestCase
{
    #[Test]
    public function leavesTheProjectCleanWhenNoLinkMissed(): void
    {
        $links = new LinkTotals();

        $outcome = new ProjectBuild(
            renderer: new SiteRenderer(),
            links   : $links,
        )->render(Targets::corpus(documents: ['index.md'])->projects[0]);

        static::assertSame([], $outcome->result->reasonCodes);
    }

    #[Test]
    public function reportsTheProjectWhosePageCarriedAnUnpublishedLink(): void
    {
        $links = new LinkTotals();

        $outcome = new ProjectBuild(
            renderer: new SiteRenderer(static function (TargetCorpus $corpus) use ($links): array {
                $links->add(LinkOutcome::Unresolved);

                return [new PageResult(
                    address   : 'https://docs.example.org/aggregate-renderer/2.0/index.html',
                    status    : PageStatus::Rendered,
                    reasonCode: null,
                    fromCache : false,
                )];
            }),
            links   : $links,
        )->render(Targets::corpus(documents: ['index.md'])->projects[0]);

        static::assertSame([ReasonCode::UnresolvedLinks], $outcome->result->reasonCodes);
    }
}
