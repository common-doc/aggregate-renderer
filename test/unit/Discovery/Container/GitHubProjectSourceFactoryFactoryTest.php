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

namespace CommonDocTest\Aggregate\Discovery\Container;

use CommonDoc\Aggregate\Discovery\Container\GitHubProjectSourceFactoryFactory;
use CommonDoc\Aggregate\Discovery\GitHubProjectSourceFactory;
use Laminas\Diactoros\RequestFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

#[CoversClass(GitHubProjectSourceFactoryFactory::class)]
#[CoversMethod(GitHubProjectSourceFactoryFactory::class, '__invoke')]
final class GitHubProjectSourceFactoryFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheFactoryFromTheRegisteredHttpSeams(): void
    {
        static::assertInstanceOf(
            GitHubProjectSourceFactory::class,
            new GitHubProjectSourceFactoryFactory()($this->container(), GitHubProjectSourceFactory::class),
        );
    }

    #[Test]
    public function reportsAMissingHttpClientAsAContainerException(): void
    {
        $this->expectException(ContainerExceptionInterface::class);

        new GitHubProjectSourceFactoryFactory()(new ServiceManager(), GitHubProjectSourceFactory::class);
    }

    private function container(): ServiceManager
    {
        $container = new ServiceManager();

        $container->setService(ClientInterface::class, $this->createStub(ClientInterface::class));
        $container->setService(RequestFactoryInterface::class, new RequestFactory());

        return $container;
    }
}
