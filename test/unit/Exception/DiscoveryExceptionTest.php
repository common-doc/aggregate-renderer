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
use CommonDoc\Aggregate\Exception\DiscoveryException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DiscoveryException::class)]
#[CoversMethod(DiscoveryException::class, 'missingToken')]
#[CoversMethod(DiscoveryException::class, 'rejectedToken')]
#[CoversMethod(DiscoveryException::class, 'unavailable')]
final class DiscoveryExceptionTest extends TestCase
{
    #[Test]
    public function namesTheMissingVariableWithoutNamingAValue(): void
    {
        $failed = DiscoveryException::missingToken('COMMONDOC_TOKEN');

        static::assertSame(ReasonCode::MissingToken, $failed->reasonCode);
        static::assertSame(
            'The environment variable "COMMONDOC_TOKEN" is unset or empty, so no organization token is available.',
            $failed->getMessage(),
        );
    }

    #[Test]
    public function recordsAnUnreachableBoundaryWithItsCause(): void
    {
        $cause  = new RuntimeException('the transport refused the connection');
        $failed = DiscoveryException::unavailable('https://api.github.com', 'it answered with status 500.', $cause);

        static::assertSame(ReasonCode::DiscoveryUnavailable, $failed->reasonCode);
        static::assertSame(
            'The boundary "https://api.github.com" is unavailable: it answered with status 500.',
            $failed->getMessage(),
        );
        static::assertSame($cause, $failed->getPrevious());
    }

    #[Test]
    public function recordsAnUnreachableBoundaryWithoutACause(): void
    {
        $failed = DiscoveryException::unavailable('https://api.github.com', 'its answer is not JSON.');

        static::assertNull($failed->getPrevious());
    }

    #[Test]
    public function recordsARejectedCredential(): void
    {
        $failed = DiscoveryException::rejectedToken('https://api.github.com/orgs/common-doc/repos');

        static::assertSame(ReasonCode::RejectedToken, $failed->reasonCode);
        static::assertSame(
            'The credential was rejected by "https://api.github.com/orgs/common-doc/repos".',
            $failed->getMessage(),
        );
    }
}
