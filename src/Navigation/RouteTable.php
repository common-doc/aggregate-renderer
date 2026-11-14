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

namespace CommonDoc\Aggregate\Navigation;

use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouteCollectorInterface;
use Mezzio\Router\RouterInterface;
use Webware\Router\QueryParamRouter;

/**
 * The routes of one build, and the router that holds them.
 *
 * The collector and the router are one thing, not two: a route is only in the router because it was
 * added through the collector, and every href the build emits is generated from the router. Handing
 * them out together means no collaborator can hold a collector whose router nobody asked for, or ask
 * a router for an href that no collector registered.
 *
 * Duplicate detection is on, so two documents that publish to one path or one name are rejected when
 * the second is registered rather than published over the first.
 */
final readonly class RouteTable
{
    public function __construct(
        public RouteCollectorInterface $collector,
        public RouterInterface $router,
    ) {}

    public static function create(): self
    {
        $router = new QueryParamRouter();

        return new self(
            collector: new RouteCollector(router: $router),
            router   : $router,
        );
    }
}
