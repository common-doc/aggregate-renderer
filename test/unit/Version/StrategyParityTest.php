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
use CommonDoc\Aggregate\Version\StrategyInterface;
use CommonDoc\Aggregate\Version\Target;
use CommonDoc\Aggregate\Version\TreeStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * The single test that proves the two strategies share one version grammar.
 *
 * One corpus is written twice, as references and as directories, and both strategies must resolve
 * it to the same ordered list. Every other property of either strategy is tested on its own.
 */
#[CoversClass(RefStrategy::class)]
#[CoversClass(TreeStrategy::class)]
#[CoversMethod(RefStrategy::class, 'resolve')]
#[CoversMethod(TreeStrategy::class, 'resolve')]
final class StrategyParityTest extends TestCase
{
    #[Test]
    public function bothStrategiesResolveOneCorpusToTheSameVersions(): void
    {
        $project = new Project('aggregate-renderer', 'common-doc', 'aggregate-renderer', 'docs');

        $references  = ['v1.2.3', '1.2.x', 'v1.10.0', '1.10.x', 'main'];
        $directories = ['1.2.3', '1.2', '1.10.0', '1.10', 'latest'];

        $fromReferences = $this->versions(new RefStrategy($project), $references);

        static::assertSame(['1.2', '1.2.3', '1.10', '1.10.0'], $fromReferences);
        static::assertSame($fromReferences, $this->versions(new TreeStrategy($project), $directories));
    }

    /**
     * @param list<non-empty-string> $candidates
     *
     * @return list<non-empty-string>
     */
    private function versions(StrategyInterface $strategy, array $candidates): array
    {
        return array_map(
            static fn(Target $target): string => $target->version,
            $strategy->resolve($candidates),
        );
    }
}
