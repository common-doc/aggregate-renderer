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

use CommonDoc\Aggregate\Source\GitCommand;
use CommonDoc\Aggregate\Source\GitFailure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function getcwd;

use const PHP_BINARY;

#[CoversClass(GitCommand::class)]
#[CoversMethod(GitCommand::class, '__construct')]
#[CoversMethod(GitCommand::class, 'run')]
final class GitCommandTest extends TestCase
{
    private static function command(): GitCommand
    {
        return new GitCommand(binary: PHP_BINARY);
    }

    private static function workingDirectory(): string
    {
        $directory = getcwd();

        static::assertIsString($directory);

        return $directory;
    }

    #[Test]
    public function passesTheEnvironmentBesideTheArgumentList(): void
    {
        $output = self::command()->run(
            arguments       : ['-r', 'echo getenv("AGGREGATE_RENDER_TEST_VALUE");'],
            workingDirectory: self::workingDirectory(),
            environment     : ['AGGREGATE_RENDER_TEST_VALUE' => 'handed-over'],
        );

        static::assertSame('handed-over', $output);
    }

    #[Test]
    public function reportsABinaryThatCannotBeStartedAsAFailure(): void
    {
        try {
            new GitCommand(binary: 'git-command-that-does-not-exist')->run(
                arguments       : ['status'],
                workingDirectory: self::workingDirectory(),
            );

            static::fail('A binary that does not exist must not produce an answer.');
        } catch (GitFailure $failure) {
            static::assertStringContainsString('The git command "git status"', $failure->getMessage());
            static::assertStringContainsString('git-command-that-does-not-exist', $failure->getMessage());
        }
    }

    #[Test]
    public function reportsANonZeroExitAsAFailure(): void
    {
        $this->expectException(GitFailure::class);
        $this->expectExceptionMessageMatches('/exited with 3: .*the command failed/s');

        self::command()->run(
            arguments       : ['-r', 'fwrite(STDERR, "the command failed"); exit(3);'],
            workingDirectory: self::workingDirectory(),
        );
    }

    #[Test]
    public function returnsTheOutputOfTheCommandUnchanged(): void
    {
        $output = self::command()->run(
            arguments       : ['-r', 'echo "  spaced  \n";'],
            workingDirectory: self::workingDirectory(),
        );

        static::assertSame("  spaced  \n", $output);
    }
}
