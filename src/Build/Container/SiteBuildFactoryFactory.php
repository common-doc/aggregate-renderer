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

namespace CommonDoc\Aggregate\Build\Container;

use CommonDoc\Aggregate\Build\SiteBuildFactory;
use CommonDoc\Aggregate\Discovery\ProjectSourceInterface;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds the build factory from the services a consumer registered.
 *
 * The build factory is the one factory in the package that cannot be a service with no dependencies:
 * it is the join point between discovery, the source, the version strategies and the renderer, and
 * every one of those is a seam a consumer may replace. Asking the container for them here means
 * replacing a seam stays a single registration, and a missing registration is reported as a missing
 * service when the command is built rather than as a fatal error when the factory is instantiated.
 */
final readonly class SiteBuildFactoryFactory implements FactoryInterface
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
    ): SiteBuildFactory {
        return new SiteBuildFactory(
            discovery : $container->get(ProjectSourceInterface::class),
            sources   : $container->get(SourceFactoryInterface::class),
            strategies: $container->get(StrategyFactory::class),
            renderers : $container->get(SiteRendererFactoryInterface::class),
        );
    }
}
