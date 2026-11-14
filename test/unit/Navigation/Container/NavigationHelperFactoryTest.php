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

namespace CommonDocTest\Aggregate\Navigation\Container;

use CommonDoc\Aggregate\Navigation\Container\NavigationHelperFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Navigation\View\Helper\Navigation;

#[CoversClass(NavigationHelperFactory::class)]
#[CoversMethod(NavigationHelperFactory::class, '__invoke')]
final class NavigationHelperFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheHelperOverTheSharedRouteTable(): void
    {
        $container = new ServiceManager();
        $container->setService(RouteTable::class, RouteTable::create());

        static::assertInstanceOf(
            Navigation::class,
            new NavigationHelperFactory()($container, Navigation::class),
        );
    }
}
