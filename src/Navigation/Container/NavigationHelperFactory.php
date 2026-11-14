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

use CommonDoc\Aggregate\Navigation\DocsSidebarRenderer;
use CommonDoc\Aggregate\Navigation\RouteTable;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Navigation\View\Helper\Navigation;

/**
 * Builds the navigation helper against this build's routes and this site's markup.
 *
 * The package ships its own helper factory, which asks the container for an access control list. This
 * build has no users and no ACL, and the helper already treats one as optional, so the factory is
 * replaced rather than the dependency being registered with a dummy. The menu renderer is replaced
 * for the same reason: the inline one emits Bootstrap classes this site does not load.
 */
final readonly class NavigationHelperFactory implements FactoryInterface
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
    ): Navigation {
        return new Navigation(
            routeCollector: $container->get(RouteTable::class)->collector,
            menuRenderer  : new DocsSidebarRenderer(),
        );
    }
}
