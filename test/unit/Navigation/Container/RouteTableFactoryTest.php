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

use CommonDoc\Aggregate\Navigation\Container\RouteTableFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Router\Exception\RuntimeException as RouterRuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteTableFactory::class)]
#[CoversMethod(RouteTableFactory::class, '__invoke')]
final class RouteTableFactoryTest extends TestCase
{
    #[Test]
    public function buildsOneTableWhoseCollectorFeedsItsRouter(): void
    {
        $table = new RouteTableFactory()(new ServiceManager(), RouteTable::class);

        static::assertInstanceOf(RouteTable::class, $table);
        $this->expectException(RouterRuntimeException::class);

        $table->router->generateUri('p|aggregate-renderer|2.0|index');
    }
}
