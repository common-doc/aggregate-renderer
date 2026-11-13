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

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Source\SourceUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SourceUnavailable::class)]
#[CoversMethod(SourceUnavailable::class, 'for')]
final class SourceUnavailableTest extends TestCase
{
    #[Test]
    public function carriesTheReasonCodeAndNamesTheProject(): void
    {
        $previous = new RuntimeException('the remote refused the connection');
        $failure  = SourceUnavailable::for(
            project : new Project(
                name        : 'aggregate-renderer',
                owner       : 'common-doc',
                sitePath    : 'aggregate-renderer',
                docDirectory: 'docs',
            ),
            reason  : 'the remote refused the connection',
            previous: $previous,
        );

        static::assertSame(ReasonCode::SourceUnavailable, $failure->reasonCode);
        static::assertStringContainsString('aggregate-renderer', $failure->getMessage());
        static::assertStringContainsString('the remote refused the connection', $failure->getMessage());
        static::assertSame($previous, $failure->getPrevious());
    }
}
