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

use CommonDoc\Aggregate\Source\GitFailure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;
use function str_repeat;
use function strlen;

#[CoversClass(GitFailure::class)]
#[CoversMethod(GitFailure::class, 'didNotRun')]
#[CoversMethod(GitFailure::class, 'failed')]
final class GitFailureTest extends TestCase
{
    #[Test]
    public function namesTheArgumentsAndTheExitCodeOfAFailedCommand(): void
    {
        $failure = GitFailure::failed(
            arguments : ['ls-remote', '--heads', 'https://example.test/repository.git'],
            exitCode  : 128,
            diagnostic: "fatal: repository not found\n",
        );

        static::assertStringContainsString(
            'ls-remote --heads https://example.test/repository.git',
            $failure->getMessage(),
        );
        static::assertStringContainsString('128', $failure->getMessage());
        static::assertStringContainsString('fatal: repository not found', $failure->getMessage());
    }

    #[Test]
    public function reportsACommandThatNeverRanWithoutAnExitCode(): void
    {
        $failure = GitFailure::didNotRun(
            arguments : ['init'],
            diagnostic: 'the binary is absent',
        );

        static::assertStringContainsString('could not be run', $failure->getMessage());
        static::assertStringContainsString('the binary is absent', $failure->getMessage());
    }

    #[Test]
    public function saysSoWhenTheCommandReportedNoDiagnostic(): void
    {
        $failure = GitFailure::failed(
            arguments : ['fetch'],
            exitCode  : 1,
            diagnostic: "  \n",
        );

        static::assertStringContainsString('no diagnostic was reported', $failure->getMessage());
    }

    #[Test]
    public function truncatesADiagnosticThatWouldSwampTheReport(): void
    {
        $failure = GitFailure::failed(
            arguments : ['fetch'],
            exitCode  : 1,
            diagnostic: str_repeat(
                string: 'a',
                times : 1000,
            ),
        );

        static::assertSame(400, strlen($failure->getMessage()) - strlen('The git command "git fetch" exited with 1: '));
        static::assertTrue(str_contains($failure->getMessage(), str_repeat(
            string: 'a',
            times : 400,
        )));
    }
}
