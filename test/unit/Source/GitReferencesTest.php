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
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Source\GitCheckout;
use CommonDoc\Aggregate\Source\GitReferences;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\Target;
use CommonDocTest\Aggregate\Support\GitRunner;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function implode;
use function mkdir;
use function sprintf;

#[CoversClass(GitReferences::class)]
#[CoversMethod(GitReferences::class, 'defaultRef')]
#[CoversMethod(GitReferences::class, 'refFor')]
#[CoversMethod(GitReferences::class, 'rootFor')]
final class GitReferencesTest extends TestCase
{
    private TemporaryDirectory $temporary;

    #[Test]
    public function asksTheRemoteForTheDefaultBranchOnlyOnce(): void
    {
        $runner     = $this->runner()->returning($this->symref());
        $references = $this->references($runner);
        $target     = $this->target('1.0');

        $references->refFor($target, Strategy::Tree);
        $references->refFor($target, Strategy::Tree);

        static::assertCount(3, $runner->calls);
        static::assertSame('ls-remote', $runner->calls[2]['arguments'][0]);
        static::assertSame('--symref', $runner->calls[2]['arguments'][1]);
        static::assertSame('HEAD', $runner->calls[2]['arguments'][3]);
    }

    #[Test]
    public function mapsAMinorVersionToItsBranch(): void
    {
        static::assertSame(
            'refs/remotes/origin/1.0.x',
            $this->references(new GitRunner())->refFor($this->target('1.0'), Strategy::Ref),
        );
    }

    #[Test]
    public function mapsAPatchVersionToItsTag(): void
    {
        static::assertSame(
            'refs/tags/v1.0.3',
            $this->references(new GitRunner())->refFor($this->target('1.0.3'), Strategy::Ref),
        );
    }

    #[Test]
    public function namesTheDocumentationDirectoryAsTheRootOfAReferenceCorpus(): void
    {
        static::assertSame(
            'docs',
            $this->references(new GitRunner())->rootFor($this->target('1.0'), Strategy::Ref),
        );
    }

    #[Test]
    public function namesTheVersionDirectoryAsTheRootOfATreeCorpus(): void
    {
        static::assertSame(
            'docs/1.0',
            $this->references(new GitRunner())->rootFor($this->target('1.0'), Strategy::Tree),
        );
    }

    #[Test]
    public function readsATreeVersionFromTheDefaultBranch(): void
    {
        $runner = $this->runner()->returning($this->symref());

        static::assertSame(
            'refs/remotes/origin/main',
            $this->references($runner)->refFor($this->target('1.0'), Strategy::Tree),
        );
    }

    #[Test]
    public function readsTheSymbolicReferenceFromALaterLineOfTheListing(): void
    {
        $runner = $this->runner()->returning("0123456789abcdef\tHEAD\nref: refs/heads/main\tHEAD\n");

        static::assertSame(
            'refs/remotes/origin/main',
            $this->references($runner)->defaultRef($this->project()),
        );
    }

    #[Test]
    public function reportsASourceThatNamesNoDefaultBranch(): void
    {
        $runner = $this->runner()->returning(implode("\n", ['0123456789abcdef', 'HEAD']));

        try {
            $this->references($runner)->defaultRef($this->project());
        } catch (SourceUnavailableException $failure) {
            static::assertSame(ReasonCode::SourceUnavailable, $failure->reasonCode);

            return;
        }

        static::fail('A remote without a symbolic head should be reported as unavailable.');
    }

    #[Test]
    public function requiresTheSymbolicReferenceToStartItsLine(): void
    {
        $runner = $this->runner()->returning("0123456789abcdef ref: refs/heads/main\tHEAD\n");

        try {
            $this->references($runner)->defaultRef($this->project());
        } catch (SourceUnavailableException $failure) {
            static::assertSame(ReasonCode::SourceUnavailable, $failure->reasonCode);

            return;
        }

        static::fail('A symbolic reference that is not at the start of its line is not one.');
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

    private function references(GitRunner $runner): GitReferences
    {
        $site = sprintf('%s/sources/aggregate-renderer', $this->temporary->path);

        static::assertTrue(mkdir(
            directory  : sprintf('%s/.git', $site),
            permissions: 0o777,
            recursive  : true,
        ));

        return new GitReferences(new GitCheckout(
            runner        : $runner,
            cacheDirectory: $this->temporary->path,
        ));
    }

    private function runner(): GitRunner
    {
        return new GitRunner()
            ->returning('')
            ->returning('');
    }

    private function symref(): string
    {
        return "ref: refs/heads/main\tHEAD\n0123456789abcdef\tHEAD\n";
    }

    private function target(string $version): Target
    {
        return new Target(
            project: $this->project(),
            version: $version,
        );
    }
}
