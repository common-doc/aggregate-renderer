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

namespace CommonDocTest\Aggregate\Exception;

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Exception\InvalidSiteConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(InvalidSiteConfigException::class)]
#[CoversMethod(InvalidSiteConfigException::class, 'at')]
final class InvalidSiteConfigExceptionTest extends TestCase
{
    private static function config(): InvalidSiteConfigException
    {
        return InvalidSiteConfigException::at(
            key   : 'organization',
            reason: 'it must be a non-empty string.',
        );
    }

    #[Test]
    public function carriesTheReasonCodeTheReportRecords(): void
    {
        static::assertSame(ReasonCode::InvalidSiteConfig, self::config()->reasonCode);
    }

    #[Test]
    public function isAnExceptionACallerCanCatchAsRuntime(): void
    {
        static::assertInstanceOf(RuntimeException::class, self::config());
    }

    #[Test]
    public function namesTheKeyItWasReading(): void
    {
        static::assertSame('organization', self::config()->key);
    }

    #[Test]
    public function statesTheKeyAndTheReasonInOneMessage(): void
    {
        static::assertSame(
            'Invalid site configuration at "organization": it must be a non-empty string.',
            self::config()->getMessage(),
        );
    }
}
