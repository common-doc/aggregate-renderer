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
use CommonDoc\Aggregate\Exception\MissingDocDirectoryException;
use CommonDoc\Aggregate\Source\GitCandidates;
use CommonDoc\Aggregate\Source\GitCheckout;
use CommonDoc\Aggregate\Source\GitReferences;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDocTest\Aggregate\Support\GitRunner;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function implode;
use function mkdir;
use function sprintf;

#[CoversClass(GitCandidates::class)]
#[CoversMethod(GitCandidates::class, 'names')]
final class GitCandidatesTest extends TestCase
{
    private TemporaryDirectory $temporary;

    #[Test]
    public function listsTheBranchesAndTagsOfTheRemoteForAReferenceCorpus(): void
    {
        $runner = $this->runner()->returning(implode("\n", [
            "0123456789abcdef\trefs/heads/1.0.x",
            "fedcba9876543210\trefs/tags/v1.0.0",
            '',
        ]));

        static::assertSame(
            ['1.0.x', 'v1.0.0'],
            $this->candidates($runner, Strategy::Ref)->names($this->project()),
        );

        static::assertSame('ls-remote', $runner->calls[2]['arguments'][0]);
        static::assertSame('--refs', $runner->calls[2]['arguments'][3]);
    }

    #[Test]
    public function listsTheSubdirectoriesOfTheDocumentationDirectoryForATreeCorpus(): void
    {
        $runner = $this
            ->runner()
            ->returning("ref: refs/heads/main\tHEAD\n")
            ->returning(implode("\n", ['docs/1.0', 'docs/1.1', '']));

        static::assertSame(
            ['1.0', '1.1'],
            $this->candidates($runner, Strategy::Tree)->names($this->project()),
        );

        static::assertSame(
            ['ls-tree', '-d', '--name-only', 'refs/remotes/origin/main', '--', 'docs/'],
            $runner->calls[count($runner->calls) - 1]['arguments'],
        );
    }

    #[Test]
    public function reportsATreeCorpusWithoutADocumentationDirectory(): void
    {
        $runner = $this->runner()->returning("ref: refs/heads/main\tHEAD\n")->returning('');

        $this->expectException(MissingDocDirectoryException::class);

        $this->candidates($runner, Strategy::Tree)->names($this->project());
    }

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    private function candidates(GitRunner $runner, Strategy $strategy): GitCandidates
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

        return new GitCandidates(
            checkouts : $checkout,
            references: new GitReferences($checkout),
            strategy  : $strategy,
        );
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

    private function runner(): GitRunner
    {
        return new GitRunner()
            ->returning('')
            ->returning('');
    }
}
