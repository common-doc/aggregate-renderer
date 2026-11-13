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

use CommonDoc\Aggregate\Build\Total;
use CommonDocTest\Aggregate\Support\Projects;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Total::class)]
#[CoversMethod(Total::class, '__construct')]
#[CoversMethod(Total::class, 'of')]
#[CoversMethod(Total::class, 'equals')]
final class TotalTest extends TestCase
{
    #[Test]
    public function comparesTheCounters(): void
    {
        $first = new Total(
            ok     : 1,
            partial: 2,
            failed : 3,
            skipped: 4,
            pages  : 5,
        );

        static::assertTrue($first->equals(new Total(
            ok     : 1,
            partial: 2,
            failed : 3,
            skipped: 4,
            pages  : 5,
        )));
        static::assertFalse($first->equals(new Total(
            ok     : 2,
            partial: 2,
            failed : 3,
            skipped: 4,
            pages  : 5,
        )));
        static::assertFalse($first->equals(new Total(
            ok     : 1,
            partial: 3,
            failed : 3,
            skipped: 4,
            pages  : 5,
        )));
        static::assertFalse($first->equals(new Total(
            ok     : 1,
            partial: 2,
            failed : 4,
            skipped: 4,
            pages  : 5,
        )));
        static::assertFalse($first->equals(new Total(
            ok     : 1,
            partial: 2,
            failed : 3,
            skipped: 5,
            pages  : 5,
        )));
        static::assertFalse($first->equals(new Total(
            ok     : 1,
            partial: 2,
            failed : 3,
            skipped: 4,
            pages  : 6,
        )));
    }

    #[Test]
    public function rejectsANegativeCounter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "failed" total is -1');

        new Total(
            ok     : 0,
            partial: 0,
            failed : -1,
            skipped: 0,
            pages  : 0,
        );
    }

    #[Test]
    public function sumsAnEmptyCorpusToZero(): void
    {
        $total = Total::of([]);

        static::assertSame(0, $total->ok);
        static::assertSame(0, $total->partial);
        static::assertSame(0, $total->failed);
        static::assertSame(0, $total->skipped);
        static::assertSame(0, $total->pages);
    }

    #[Test]
    public function sumsTheProjectsIntoTheCounters(): void
    {
        $total = Total::of([
            Projects::ok(),
            Projects::partial(sitePath: 'commondoc'),
            Projects::failed(sitePath: 'phpdb'),
            Projects::skipped(sitePath: 'webware-tools'),
        ]);

        static::assertSame(1, $total->ok);
        static::assertSame(1, $total->partial);
        static::assertSame(1, $total->failed);
        static::assertSame(1, $total->skipped);
        static::assertSame(2, $total->pages);
    }
}
