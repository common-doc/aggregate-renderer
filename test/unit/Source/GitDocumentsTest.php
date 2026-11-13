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
use CommonDoc\Aggregate\Source\GitCheckout;
use CommonDoc\Aggregate\Source\GitDocuments;
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

#[CoversClass(GitDocuments::class)]
#[CoversMethod(GitDocuments::class, 'contents')]
#[CoversMethod(GitDocuments::class, 'paths')]
final class GitDocumentsTest extends TestCase
{
    private TemporaryDirectory $temporary;

    #[Test]
    public function listsTheDocumentsOfATreeCorpusUnderTheVersionDirectory(): void
    {
        $runner = $this
            ->runner()
            ->returning("ref: refs/heads/main\tHEAD\n")
            ->returning(implode("\n", ['docs/1.0/index.md', 'docs/1.0/guide.md', 'docs/1.1/index.md', '']));

        static::assertSame(
            ['index.md', 'guide.md'],
            $this->documents($runner, Strategy::Tree)->paths($this->target('1.0')),
        );
    }

    #[Test]
    public function listsTheMarkdownDocumentsOfAReferenceCorpus(): void
    {
        $runner = $this->runner()->returning(implode("\n", [
            'docs/guide/index.md',
            'docs/install.md',
            'docs/logo.svg',
            '',
        ]));

        static::assertSame(
            ['guide/index.md', 'install.md'],
            $this->documents($runner, Strategy::Ref)->paths($this->target('1.0')),
        );

        static::assertSame(
            ['ls-tree', '-r', '--name-only', 'refs/remotes/origin/1.0.x', '--', 'docs/'],
            $runner->calls[2]['arguments'],
        );
    }

    #[Test]
    public function readsOneDocumentAtTheReferenceRatherThanTheBranchTip(): void
    {
        $runner = $this->runner()->returning("# Aggregate renderer\n");

        static::assertSame(
            "# Aggregate renderer\n",
            $this->documents($runner, Strategy::Ref)->contents($this->target('1.0'), 'index.md'),
        );

        static::assertSame(
            ['show', 'refs/remotes/origin/1.0.x:docs/index.md'],
            $runner->calls[2]['arguments'],
        );
    }

    #[Test]
    public function reportsATargetWithoutDocuments(): void
    {
        $this->expectException(MissingDocDirectoryException::class);

        $this->documents($this->runner()->returning(''), Strategy::Ref)->paths($this->target('1.0'));
    }

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    private function documents(GitRunner $runner, Strategy $strategy): GitDocuments
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

        return new GitDocuments(
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

    private function target(string $version): Target
    {
        return new Target(
            project: $this->project(),
            version: $version,
        );
    }
}
