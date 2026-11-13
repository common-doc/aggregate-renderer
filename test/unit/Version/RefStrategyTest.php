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
use CommonDoc\Aggregate\Version\RefStrategy;
use CommonDoc\Aggregate\Version\Target;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_reverse;

#[CoversClass(RefStrategy::class)]
#[CoversMethod(RefStrategy::class, 'resolve')]
final class RefStrategyTest extends TestCase
{
    #[Test]
    public function deDuplicatesARepeatedReference(): void
    {
        static::assertSame(['1.2', '1.2.3'], $this->versions(['v1.2.3', 'v1.2.3', '1.2.x', '1.2.x']));
    }

    #[Test]
    public function excludesReferencesThatAreNotVersions(): void
    {
        $candidates = [
            'main',
            'v1',
            'v1.2',
            '1.2.3',
            '1.2',
            'release-1.2.3',
            'v1.2.3-rc.1',
            'fix/1.2.x',
        ];

        static::assertSame([], $this->versions($candidates));
    }

    #[Test]
    public function isInvariantUnderCandidateOrder(): void
    {
        $candidates = ['v2.0.0', '1.10.x', 'v1.9.0', 'main'];

        static::assertSame(
            $this->versions($candidates),
            $this->versions(array_reverse($candidates)),
        );
    }

    #[Test]
    public function ordersVersionsNumericallyRatherThanLexically(): void
    {
        $candidates = ['v1.9.0', 'v1.10.0', '1.2.x', 'v1.10.1', 'v1.9.10'];

        static::assertSame(['1.2', '1.9.0', '1.9.10', '1.10.0', '1.10.1'], $this->versions($candidates));
    }

    #[Test]
    public function resolvesAMaintenanceReferenceToAMinorVersion(): void
    {
        static::assertSame(['1.2'], $this->versions(['1.2.x']));
    }

    #[Test]
    public function resolvesAnEmptyCandidateListToNoTargets(): void
    {
        static::assertSame([], $this->versions([]));
    }

    #[Test]
    public function resolvesAReleaseReferenceToAPatchVersion(): void
    {
        static::assertSame(['1.2.3'], $this->versions(['v1.2.3']));
    }

    private function strategy(): RefStrategy
    {
        return new RefStrategy(
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
