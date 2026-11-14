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

namespace CommonDoc\Aggregate\Navigation\Container;

use CommonDoc\Aggregate\Navigation\RouteTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Builds the route table the whole build shares.
 *
 * The table is a named constructor rather than an invokable, because the collector and the router it
 * holds have to be the same pair: constructing them separately is how a build ends up registering
 * routes in one collector and asking a different router for hrefs.
 */
final readonly class RouteTableFactory implements FactoryInterface
{
    /**
     * @param ?array<mixed> $options
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): RouteTable {
        return RouteTable::create();
    }
}
