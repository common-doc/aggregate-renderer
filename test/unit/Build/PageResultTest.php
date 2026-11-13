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

use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Build\ReasonCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageResult::class)]
#[CoversMethod(PageResult::class, '__construct')]
final class PageResultTest extends TestCase
{
    #[Test]
    public function recordsAFailedPageWithItsReason(): void
    {
        $page = new PageResult(
            address   : 'https://docs.example.org/aggregate-renderer/1.0/guide/',
            status    : PageStatus::Failed,
            reasonCode: ReasonCode::RenderFailed,
            fromCache : false,
        );

        static::assertSame(PageStatus::Failed, $page->status);
        static::assertSame(ReasonCode::RenderFailed, $page->reasonCode);
    }

    #[Test]
    public function recordsARenderedPageServedFromCache(): void
    {
        $page = new PageResult(
            address   : 'https://docs.example.org/aggregate-renderer/1.0/',
            status    : PageStatus::Rendered,
            reasonCode: null,
            fromCache : true,
        );

        static::assertTrue($page->fromCache);
    }

    #[Test]
    public function recordsARenderedPageWithoutAReason(): void
    {
        $page = new PageResult(
            address   : 'https://docs.example.org/aggregate-renderer/1.0/',
            status    : PageStatus::Rendered,
            reasonCode: null,
            fromCache : false,
        );

        static::assertSame('https://docs.example.org/aggregate-renderer/1.0/', $page->address);
        static::assertSame(PageStatus::Rendered, $page->status);
        static::assertNull($page->reasonCode);
        static::assertFalse($page->fromCache);
    }

    #[Test]
    public function rejectsAFailedPageWithoutAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carries no reason code');

        new PageResult(
            address   : 'https://docs.example.org/aggregate-renderer/1.0/',
            status    : PageStatus::Failed,
            reasonCode: null,
            fromCache : false,
        );
    }

    #[Test]
    public function rejectsAnEmptyAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a non-empty address');

        new PageResult(
            address   : '',
            status    : PageStatus::Rendered,
            reasonCode: null,
            fromCache : false,
        );
    }

    #[Test]
    public function rejectsARenderedPageThatCarriesAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carries the reason code "render-failed"');

        new PageResult(
            address   : 'https://docs.example.org/aggregate-renderer/1.0/',
            status    : PageStatus::Rendered,
            reasonCode: ReasonCode::RenderFailed,
            fromCache : false,
        );
    }
}
