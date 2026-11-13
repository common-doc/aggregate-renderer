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

use CommonDoc\Aggregate\Source\SourceFactory;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDocTest\Aggregate\Support\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceFactory::class)]
#[CoversMethod(SourceFactory::class, 'create')]
final class SourceFactoryTest extends TestCase
{
    #[Test]
    public function buildsAFreshSourceOnEveryCall(): void
    {
        $factory = new SourceFactory();

        static::assertNotSame(
            $factory->create(config: Config::site()),
            $factory->create(config: Config::site()),
        );
    }

    #[Test]
    public function buildsASource(): void
    {
        $factory = new SourceFactory();

        static::assertInstanceOf(SourceFactoryInterface::class, $factory);
        static::assertInstanceOf(SourceInterface::class, $factory->create(config: Config::site()));
    }

    #[Test]
    public function buildsASourceForEveryStrategy(): void
    {
        foreach (Strategy::cases() as $strategy) {
            static::assertInstanceOf(
                SourceInterface::class,
                new SourceFactory()->create(config: Config::site(strategy: $strategy)),
            );
        }
    }
}
