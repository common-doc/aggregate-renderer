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

namespace CommonDocTest\Aggregate\Console\Container;

use CommonDoc\Aggregate\Build\SiteBuildFactoryInterface;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\Console\BuildCommand;
use CommonDoc\Aggregate\Console\Container\BuildCommandFactory;
use CommonDoc\Aggregate\Report\ReportFileFactoryInterface;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuildCommandFactory::class)]
#[CoversMethod(BuildCommandFactory::class, '__invoke')]
final class BuildCommandFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheCommandFromTheRegisteredServices(): void
    {
        static::assertInstanceOf(
            BuildCommand::class,
            new BuildCommandFactory()($this->container(), BuildCommand::class),
        );
    }

    #[Test]
    public function theCommandItBuildsReadsTheConfigurationItIsPointedAt(): void
    {
        $command = new BuildCommandFactory()($this->container(), BuildCommand::class);

        static::assertTrue($command->getDefinition()->hasOption('site'));
        static::assertTrue($command->getDefinition()->hasOption('json'));
    }

    private function container(): ServiceManager
    {
        $container = new ServiceManager();

        $container->setService(SiteConfigReader::class, new SiteConfigReader());
        $container->setService(
            SiteBuildFactoryInterface::class,
            $this->createStub(SiteBuildFactoryInterface::class),
        );
        $container->setService(
            ReportFileFactoryInterface::class,
            $this->createStub(ReportFileFactoryInterface::class),
        );

        return $container;
    }
}
