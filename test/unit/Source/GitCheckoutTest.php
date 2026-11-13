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
use CommonDoc\Aggregate\Exception\GitFailureException;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Source\GitCheckout;
use CommonDocTest\Aggregate\Support\GitRunner;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_writable;
use function mkdir;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

#[CoversClass(GitCheckout::class)]
#[CoversMethod(GitCheckout::class, '__construct')]
#[CoversMethod(GitCheckout::class, 'directory')]
#[CoversMethod(GitCheckout::class, 'run')]
#[CoversMethod(GitCheckout::class, 'urlOf')]
final class GitCheckoutTest extends TestCase
{
    private TemporaryDirectory $temporary;

    #[Test]
    public function buildsTheRemoteUrlFromTheBaseAndTheProject(): void
    {
        $checkout = new GitCheckout(
            runner        : new GitRunner(),
            cacheDirectory: $this->temporary->path,
            remoteBase    : 'https://example.test/',
        );

        static::assertSame(
            'https://example.test/common-doc/aggregate-renderer.git',
            $checkout->urlOf($this->project()),
        );
    }

    #[Test]
    public function preparesAFreshCheckoutAndFetchesIntoIt(): void
    {
        $runner   = new GitRunner();
        $checkout = new GitCheckout(
            runner        : $runner,
            cacheDirectory: $this->temporary->path,
        );
        $expected = sprintf('%s/sources/aggregate-renderer', $this->temporary->path);

        static::assertSame($expected, $checkout->directory($this->project()));
        static::assertDirectoryExists($expected);
        static::assertTrue(is_writable($expected));

        static::assertSame(['init', '--quiet'], $runner->calls[0]['arguments']);
        static::assertSame(
            ['remote', 'add', 'origin', 'https://github.com/common-doc/aggregate-renderer.git'],
            $runner->calls[1]['arguments'],
        );
        static::assertSame('fetch', $runner->calls[2]['arguments'][0]);
        static::assertSame($expected, $runner->calls[2]['workingDirectory']);
    }

    #[Test]
    public function preparesOneProjectOnlyOnce(): void
    {
        $runner   = new GitRunner();
        $checkout = new GitCheckout(
            runner        : $runner,
            cacheDirectory: $this->temporary->path,
        );

        $checkout->directory($this->project());
        $checkout->directory($this->project());

        static::assertCount(3, $runner->calls);
    }

    #[Test]
    public function rejectsAnEmptyCacheDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GitCheckout(
            runner        : new GitRunner(),
            cacheDirectory: '/',
        );
    }

    #[Test]
    public function repointsAnExistingCheckoutRatherThanReinitialisingIt(): void
    {
        $runner = new GitRunner();
        $site   = sprintf('%s/sources/aggregate-renderer', $this->temporary->path);

        static::assertTrue(mkdir(
            directory  : sprintf('%s/.git', $site),
            permissions: 0o777,
            recursive  : true,
        ));

        $checkout = new GitCheckout(
            runner        : $runner,
            cacheDirectory: $this->temporary->path,
        );

        static::assertSame($site, $checkout->directory($this->project()));
        static::assertSame('remote', $runner->calls[0]['arguments'][0]);
        static::assertSame('set-url', $runner->calls[0]['arguments'][1]);
    }

    #[Test]
    public function reportsACacheDirectoryThatCannotBeCreated(): void
    {
        $sources = sprintf('%s/sources', $this->temporary->path);

        static::assertTrue(mkdir(
            directory  : $sources,
            permissions: 0o777,
            recursive  : true,
        ));
        static::assertNotFalse(file_put_contents(
            filename: sprintf('%s/aggregate-renderer', $sources),
            data    : 'not a directory',
        ));

        $checkout = new GitCheckout(
            runner        : new GitRunner(),
            cacheDirectory: $this->temporary->path,
        );

        set_error_handler(static fn(): bool => true);

        try {
            $checkout->directory($this->project());
        } catch (SourceUnavailableException $failure) {
            static::assertSame(ReasonCode::SourceUnavailable, $failure->reasonCode);
            static::assertStringContainsString(
                sprintf(
                    'The cache directory "%s/sources/aggregate-renderer" could not be created.',
                    $this->temporary->path,
                ),
                $failure->getMessage(),
            );

            return;
        } finally {
            restore_error_handler();
        }

        static::fail('A cache directory that cannot be created makes the source unavailable.');
    }

    #[Test]
    public function reportsAFailedCommandAsAnUnavailableSource(): void
    {
        $runner = new GitRunner();
        $runner->failing(GitFailureException::failed(
            arguments : ['init', '--quiet'],
            exitCode  : 128,
            diagnostic: 'boom',
        ));

        $checkout = new GitCheckout(
            runner        : $runner,
            cacheDirectory: $this->temporary->path,
        );

        try {
            $checkout->directory($this->project());
        } catch (SourceUnavailableException $failure) {
            static::assertSame(ReasonCode::SourceUnavailable, $failure->reasonCode);

            return;
        }

        static::fail('The checkout should have reported an unavailable source.');
    }

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    private function project(): Project
    {
        return new Project(
            name        : 'aggregate-renderer',
            owner       : 'common-doc',
            sitePath    : 'aggregate-renderer',
            docDirectory: 'docs',
        );
    }
}
