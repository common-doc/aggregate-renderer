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

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Source\GitCandidates;
use CommonDoc\Aggregate\Source\GitCheckout;
use CommonDoc\Aggregate\Source\GitDocuments;
use CommonDoc\Aggregate\Source\GitReferences;
use CommonDoc\Aggregate\Source\GitSource;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\Target;
use CommonDocTest\Aggregate\Support\GitRunner;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function mkdir;
use function sprintf;

#[CoversClass(GitSource::class)]
#[CoversMethod(GitSource::class, '__construct')]
#[CoversMethod(GitSource::class, 'candidates')]
#[CoversMethod(GitSource::class, 'contents')]
#[CoversMethod(GitSource::class, 'documents')]
final class GitSourceTest extends TestCase
{
    private TemporaryDirectory $temporary;

    #[Test]
    public function answersTheThreeQuestionsOfTheSeam(): void
    {
        $runner = new GitRunner()
            ->returning('')
            ->returning('')
            ->returning("0123456789abcdef\trefs/heads/1.0.x\n")
            ->returning("docs/index.md\n")
            ->returning("# Aggregate renderer\n");

        $source = $this->source($runner);

        static::assertSame(['1.0.x'], $source->candidates($this->project()));
        static::assertSame(['index.md'], $source->documents($this->target()));
        static::assertSame("# Aggregate renderer\n", $source->contents($this->target(), 'index.md'));
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

    private function source(GitRunner $runner): GitSource
    {
        $site = sprintf('%s/sources/aggregate-renderer', $this->temporary->path);

        static::assertTrue(mkdir(
            directory  : sprintf('%s/.git', $site),
            permissions: 0o777,
            recursive  : true,
        ));

        $checkout = new GitCheckout(
            runner        : $runner,
            cacheDirectory: $this->temporary->path,
        );
        $references = new GitReferences($checkout);

        return new GitSource(
            candidates: new GitCandidates(
                checkouts : $checkout,
                references: $references,
                strategy  : Strategy::Ref,
            ),
            documents: new GitDocuments(
                checkouts : $checkout,
                references: $references,
                strategy  : Strategy::Ref,
            ),
        );
    }

    private function target(): Target
    {
        return new Target(
            project: $this->project(),
            version: '1.0',
        );
    }
}
