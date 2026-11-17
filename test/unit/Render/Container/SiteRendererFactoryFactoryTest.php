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

namespace CommonDocTest\Aggregate\Render\Container;

use CommonDoc\Aggregate\Build\LinkTotal;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\Container\SiteRendererFactoryFactory;
use CommonDoc\Aggregate\Render\SiteRendererFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Navigation\View\Helper\Navigation;

#[CoversClass(SiteRendererFactoryFactory::class)]
#[CoversMethod(SiteRendererFactoryFactory::class, '__invoke')]
final class SiteRendererFactoryFactoryTest extends TestCase
{
    #[Test]
    public function buildsTheFactoryOverTheSharedCollaborators(): void
    {
        $container = new ServiceManager();
        $table     = RouteTable::create();
        $container->setService(RouteTable::class, $table);
        $container->setService(Navigation::class, new Navigation($table->collector));
        $container->setService(LinkTotal::class, new LinkTotal());

        static::assertInstanceOf(
            SiteRendererFactory::class,
            new SiteRendererFactoryFactory()($container, SiteRendererFactory::class),
        );
    }
}
