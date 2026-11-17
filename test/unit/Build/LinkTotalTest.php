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
use CommonDoc\Aggregate\Build\LinkTotal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(LinkTotal::class)]
#[CoversMethod(LinkTotal::class, 'add')]
#[CoversMethod(LinkTotal::class, 'count')]
#[CoversMethod(LinkTotal::class, 'reset')]
#[CoversMethod(LinkTotal::class, 'toArray')]
final class LinkTotalTest extends TestCase
{
    #[Test]
    public function countsAnOutcomeThatWasNeverAddedAsZero(): void
    {
        static::assertSame(0, new LinkTotal()->count(LinkOutcome::External));
    }

    #[Test]
    public function countsEachOutcomeSeparately(): void
    {
        $totals = new LinkTotal();

        $totals->add(LinkOutcome::Document);
        $totals->add(LinkOutcome::Document);
        $totals->add(LinkOutcome::Unresolved);

        static::assertSame(2, $totals->count(LinkOutcome::Document));
        static::assertSame(1, $totals->count(LinkOutcome::Unresolved));
    }

    #[Test]
    public function resetReturnsEveryCountToZero(): void
    {
        $totals = new LinkTotal();
        $totals->add(LinkOutcome::Document);

        $totals->reset();

        static::assertSame(0, $totals->count(LinkOutcome::Document));

        foreach ($totals->toArray() as $count) {
            static::assertSame(0, $count);
        }
    }

    #[Test]
    public function serialisesEveryOutcomeInDeclarationOrder(): void
    {
        $totals = new LinkTotal();
        $totals->add(LinkOutcome::Document);
        $totals->add(LinkOutcome::NonDocument);

        $serialised = $totals->toArray();

        static::assertSame(1, $serialised[LinkOutcome::Document->value]);
        static::assertSame(1, $serialised[LinkOutcome::NonDocument->value]);
        static::assertSame(0, $serialised[LinkOutcome::Unresolved->value]);
        static::assertCount(count(LinkOutcome::cases()), $serialised);
    }
}
