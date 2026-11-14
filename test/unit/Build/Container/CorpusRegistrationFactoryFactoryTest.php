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

namespace CommonDocTest\Aggregate\Build\Container;

use CommonDoc\Aggregate\Build\Container\CorpusRegistrationFactoryFactory;
use CommonDoc\Aggregate\Build\CorpusRegistrationFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Version\StrategyFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorpusRegistrationFactoryFactory::class)]
#[CoversMethod(CorpusRegistrationFactoryFactory::class, '__invoke')]
final class CorpusRegistrationFactoryFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheCorpusRegistrationFactory(): void
    {
        $container = new ServiceManager();
        $container->setService(StrategyFactory::class, new StrategyFactory());
        $container->setService(RouteTable::class, RouteTable::create());

        static::assertInstanceOf(
            CorpusRegistrationFactory::class,
            new CorpusRegistrationFactoryFactory()($container, CorpusRegistrationFactory::class),
        );
    }
}
