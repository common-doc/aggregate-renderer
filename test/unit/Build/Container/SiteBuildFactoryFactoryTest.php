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

use CommonDoc\Aggregate\Build\Container\SiteBuildFactoryFactory;
use CommonDoc\Aggregate\Build\SiteBuildFactory;
use CommonDoc\Aggregate\Discovery\ProjectSourceFactoryInterface;
use CommonDoc\Aggregate\Render\SiteRendererFactory;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Source\SourceFactory;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;

#[CoversClass(SiteBuildFactoryFactory::class)]
#[CoversMethod(SiteBuildFactoryFactory::class, '__invoke')]
final class SiteBuildFactoryFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheBuildFactoryFromTheRegisteredSeams(): void
    {
        static::assertInstanceOf(
            SiteBuildFactory::class,
            new SiteBuildFactoryFactory()($this->container(), SiteBuildFactory::class),
        );
    }

    #[Test]
    public function reportsAMissingSeamAsAContainerException(): void
    {
        $container = new ServiceManager();
        $container->setService(SourceFactoryInterface::class, new SourceFactory());
        $container->setService(StrategyFactory::class, new StrategyFactory());
        $container->setService(SiteRendererFactoryInterface::class, new SiteRendererFactory());

        $this->expectException(ContainerExceptionInterface::class);

        new SiteBuildFactoryFactory()($container, SiteBuildFactory::class);
    }

    private function container(): ServiceManager
    {
        $container = new ServiceManager();

        $container->setService(
            ProjectSourceFactoryInterface::class,
            $this->createStub(ProjectSourceFactoryInterface::class),
        );
        $container->setService(SourceFactoryInterface::class, new SourceFactory());
        $container->setService(StrategyFactory::class, new StrategyFactory());
        $container->setService(SiteRendererFactoryInterface::class, new SiteRendererFactory());

        return $container;
    }
}
