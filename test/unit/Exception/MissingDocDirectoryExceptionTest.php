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
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Exception\MissingDocDirectoryException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MissingDocDirectoryException::class)]
#[CoversMethod(MissingDocDirectoryException::class, 'at')]
final class MissingDocDirectoryExceptionTest extends TestCase
{
    #[Test]
    public function namesTheDirectoryTheProjectAndTheRef(): void
    {
        $failure = MissingDocDirectoryException::at(
            project: new Project(
                name        : 'aggregate-renderer',
                owner       : 'common-doc',
                sitePath    : 'aggregate-renderer',
                docDirectory: 'docs',
            ),
            ref    : 'refs/remotes/origin/main',
        );

        static::assertSame(ReasonCode::MissingDocDirectory, $failure->reasonCode);
        static::assertStringContainsString('docs', $failure->getMessage());
        static::assertStringContainsString('aggregate-renderer', $failure->getMessage());
        static::assertStringContainsString('refs/remotes/origin/main', $failure->getMessage());
    }
}
