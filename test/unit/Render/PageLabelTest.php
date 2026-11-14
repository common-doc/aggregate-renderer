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

namespace CommonDocTest\Aggregate\Render;

use CommonDoc\Aggregate\Render\PageLabel;
use CommonDocTest\Aggregate\Support\Targets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageLabel::class)]
#[CoversMethod(PageLabel::class, 'for')]
final class PageLabelTest extends TestCase
{
    #[Test]
    public function humanisesAPlainDocumentName(): void
    {
        static::assertSame(
            'Flow Charts',
            PageLabel::for(Targets::target(), 'flow-charts.md'),
        );
        static::assertSame(
            'Getting Started',
            PageLabel::for(Targets::target(), 'guide/getting_started.md'),
        );
    }

    #[Test]
    public function namesTheContainingDirectoryForANestedLandingPage(): void
    {
        static::assertSame(
            'Get Started',
            PageLabel::for(Targets::target(), 'get-started/index.md'),
        );
        static::assertSame(
            'Reference',
            PageLabel::for(Targets::target(), 'api/reference/index.md'),
        );
    }

    #[Test]
    public function namesTheProjectForTheVersionLandingPage(): void
    {
        static::assertSame('aggregate-renderer', PageLabel::for(Targets::target(), 'index.md'));
    }
}
