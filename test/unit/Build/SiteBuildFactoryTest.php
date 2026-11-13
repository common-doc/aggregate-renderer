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

namespace CommonDocTest\Aggregate\Build;

use CommonDoc\Aggregate\Build\SiteBuild;
use CommonDoc\Aggregate\Build\SiteBuildFactory;
use CommonDoc\Aggregate\Build\SiteBuildFactoryInterface;
use CommonDoc\Aggregate\Discovery\ProjectSourceInterface;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Render\SiteRendererInterface;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use CommonDocTest\Aggregate\Support\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteBuildFactory::class)]
#[CoversMethod(SiteBuildFactory::class, '__construct')]
#[CoversMethod(SiteBuildFactory::class, 'create')]
final class SiteBuildFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheSourceOnceAndHandsItToBothCollaborators(): void
    {
        $config   = Config::site();
        $source   = $this->createStub(SourceInterface::class);
        $renderer = $this->createStub(SiteRendererInterface::class);

        $sources = $this->createMock(SourceFactoryInterface::class);
        $sources
            ->expects($this->once())
            ->method('create')
            ->with($config)
            ->willReturn($source);

        $renderers = $this->createMock(SiteRendererFactoryInterface::class);
        $renderers
            ->expects($this->once())
            ->method('create')
            ->with($config, $source)
            ->willReturn($renderer);

        static::assertInstanceOf(
            SiteBuild::class,
            $this->factory(
                sources  : $sources,
                renderers: $renderers,
            )->create(config: $config),
        );
    }

    #[Test]
    public function implementsTheInterfaceTheContainerResolves(): void
    {
        static::assertInstanceOf(SiteBuildFactoryInterface::class, $this->factory());
    }

    private function factory(
        ?SourceFactoryInterface $sources = null,
        ?SiteRendererFactoryInterface $renderers = null,
    ): SiteBuildFactory {
        return new SiteBuildFactory(
            discovery : $this->createStub(ProjectSourceInterface::class),
            sources   : $sources ?? $this->createStub(SourceFactoryInterface::class),
            strategies: new StrategyFactory(),
            renderers : $renderers ?? $this->createStub(SiteRendererFactoryInterface::class),
        );
    }
}
