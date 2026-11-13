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
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\StrategyFactory;
use CommonDoc\Aggregate\Version\TreeStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StrategyFactory::class)]
#[CoversMethod(StrategyFactory::class, 'create')]
final class StrategyFactoryTest extends TestCase
{
    private static function project(): Project
    {
        return new Project(
            name        : 'aggregate-renderer',
            owner       : 'common-doc',
            sitePath    : 'aggregate-renderer',
            docDirectory: 'docs',
        );
    }

    #[Test]
    public function buildsTheRefStrategyForTheRefCorpus(): void
    {
        $strategy = new StrategyFactory()->create(
            strategy: Strategy::Ref,
            project : self::project(),
        );

        static::assertInstanceOf(RefStrategy::class, $strategy);
    }

    #[Test]
    public function buildsTheTreeStrategyForTheTreeCorpus(): void
    {
        $strategy = new StrategyFactory()->create(
            strategy: Strategy::Tree,
            project : self::project(),
        );

        static::assertInstanceOf(TreeStrategy::class, $strategy);
    }
}
