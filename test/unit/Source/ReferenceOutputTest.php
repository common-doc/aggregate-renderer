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

namespace CommonDocTest\Aggregate\Source;

use CommonDoc\Aggregate\Source\ReferenceOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function implode;

#[CoversClass(ReferenceOutput::class)]
#[CoversMethod(ReferenceOutput::class, 'names')]
final class ReferenceOutputTest extends TestCase
{
    #[Test]
    public function dropsALineThatNamesNeitherABranchNorATag(): void
    {
        $listing = implode("\n", [
            "0123456789abcdef\tHEAD",
            "0123456789abcdef\trefs/pull/4/head",
            "0123456789abcdef\trefs/heads/1.0.x",
        ]);

        static::assertSame(['1.0.x'], ReferenceOutput::names($listing));
    }

    #[Test]
    public function reportsNothingForAnEmptyListing(): void
    {
        static::assertSame([], ReferenceOutput::names(''));
    }

    #[Test]
    public function stripsTheBranchAndTagPrefixes(): void
    {
        $listing = implode("\n", [
            "0123456789abcdef\trefs/heads/1.0.x",
            "0123456789abcdef\trefs/heads/main",
            "fedcba9876543210\trefs/tags/v1.0.0",
            '',
        ]);

        static::assertSame(
            ['1.0.x', 'main', 'v1.0.0'],
            ReferenceOutput::names($listing),
        );
    }
}
