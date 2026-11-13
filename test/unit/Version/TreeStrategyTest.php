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

namespace CommonDocTest\Aggregate\Version;

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Version\Target;
use CommonDoc\Aggregate\Version\TreeStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_reverse;

#[CoversClass(TreeStrategy::class)]
#[CoversMethod(TreeStrategy::class, 'resolve')]
final class TreeStrategyTest extends TestCase
{
    #[Test]
    public function deDuplicatesARepeatedDirectoryName(): void
    {
        static::assertSame(['1.2'], $this->versions(['1.2', '1.2']));
    }

    #[Test]
    public function excludesDirectoryNamesThatAreNotVersions(): void
    {
        $candidates = ['latest', 'docs', '1', 'v1.2.3', '1.2.3.4', '1.x', 'release-notes'];

        static::assertSame([], $this->versions($candidates));
    }

    #[Test]
    public function isInvariantUnderCandidateOrder(): void
    {
        $candidates = ['2.0.0', '1.10', '1.9.0', 'latest'];

        static::assertSame(
            $this->versions($candidates),
            $this->versions(array_reverse($candidates)),
        );
    }

    #[Test]
    public function ordersVersionsNumericallyRatherThanLexically(): void
    {
        $candidates = ['1.10', '1.9', '1.2.3', '1.10.0'];

        static::assertSame(['1.2.3', '1.9', '1.10', '1.10.0'], $this->versions($candidates));
    }

    #[Test]
    public function resolvesAMinorVersionDirectory(): void
    {
        static::assertSame(['1.2'], $this->versions(['1.2']));
    }

    #[Test]
    public function resolvesAnEmptyCandidateListToNoTargets(): void
    {
        static::assertSame([], $this->versions([]));
    }

    #[Test]
    public function resolvesAPatchVersionDirectory(): void
    {
        static::assertSame(['1.2.3'], $this->versions(['1.2.3']));
    }

    private function strategy(): TreeStrategy
    {
        return new TreeStrategy(
            new Project('aggregate-renderer', 'common-doc', 'aggregate-renderer', 'docs'),
        );
    }

    /**
     * @param list<non-empty-string> $candidates
     *
     * @return list<non-empty-string>
     */
    private function versions(array $candidates): array
    {
        return array_map(
            static fn(Target $target): string => $target->version,
            $this->strategy()->resolve($candidates),
        );
    }
}
