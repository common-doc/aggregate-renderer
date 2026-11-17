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

namespace CommonDoc\Aggregate\Render\Container;

use CommonDoc\Aggregate\Build\LinkTotal;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\SiteRendererFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Navigation\View\Helper\Navigation;

/**
 * Builds the renderer factory from the services the build shares.
 *
 * The route table, the navigation helper and the link counters are all one-per-build state, so they
 * are asked for here rather than created here: the same table the build registers routes into is the
 * one the rewriter asks for hrefs, and the same counters the pages add to are the ones the report
 * serialises.
 */
final readonly class SiteRendererFactoryFactory implements FactoryInterface
{
    /**
     * @param ?array<mixed> $options
     *
     * @throws ContainerExceptionInterface When a service cannot be created
     * @throws NotFoundExceptionInterface  When a service is not registered
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): SiteRendererFactory {
        return new SiteRendererFactory(
            routes    : $container->get(RouteTable::class),
            navigation: $container->get(Navigation::class),
            links     : $container->get(LinkTotal::class),
        );
    }
}
