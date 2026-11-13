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

namespace CommonDocTest\Aggregate\Discovery;

use CommonDoc\Aggregate\Discovery\Project;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Project::class)]
#[CoversMethod(Project::class, '__construct')]
final class ProjectTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function derivedSitePaths(): array
    {
        return [
            'single word' => ['aggregate'],
            'two words'   => ['aggregate-renderer'],
            'with digits' => ['phpdb-2'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function underivedSitePaths(): array
    {
        return [
            'upper case'          => ['Aggregate'],
            'underscore'          => ['aggregate_renderer'],
            'double hyphen'       => ['aggregate--renderer'],
            'leading hyphen'      => ['-aggregate'],
            'trailing hyphen'     => ['aggregate-'],
            'embedded whitespace' => ['aggregate renderer'],
            'embedded dot'        => ['aggregate.renderer'],
        ];
    }

    #[Test]
    #[DataProvider('derivedSitePaths')]
    public function acceptsADerivedSitePath(string $sitePath): void
    {
        $project = new Project('repository', 'common-doc', $sitePath, 'docs');

        static::assertSame($sitePath, $project->sitePath);
    }

    #[Test]
    public function carriesTheValuesItWasConstructedWith(): void
    {
        $project = new Project('aggregate-renderer', 'common-doc', 'aggregate-renderer', 'docs');

        static::assertSame('aggregate-renderer', $project->name);
        static::assertSame('common-doc', $project->owner);
        static::assertSame('aggregate-renderer', $project->sitePath);
        static::assertSame('docs', $project->docDirectory);
    }

    #[Test]
    public function rejectsAnEmptyDocumentationDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Project('repository', 'common-doc', 'repository', '');
    }

    #[Test]
    public function rejectsAnEmptyOwner(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Project('repository', '', 'repository', 'docs');
    }

    #[Test]
    public function rejectsAnEmptyRepositoryName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Project('', 'common-doc', 'repository', 'docs');
    }

    #[Test]
    public function rejectsAnEmptySitePath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Project('repository', 'common-doc', '', 'docs');
    }

    #[Test]
    #[DataProvider('underivedSitePaths')]
    public function rejectsASitePathThatIsNotDerived(string $sitePath): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Project('repository', 'common-doc', $sitePath, 'docs');
    }
}
