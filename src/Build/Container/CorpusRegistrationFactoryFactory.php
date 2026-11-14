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

use CommonDoc\Aggregate\Build\CorpusRegistrationFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Version\StrategyFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Builds the corpus registration factory from the services the build shares.
 *
 * The version strategies and the route table are one-per-build, so they are asked for rather than
 * created: the same table the registration adds to is the one the link rewriter asks for hrefs.
 */
final readonly class CorpusRegistrationFactoryFactory implements FactoryInterface
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
    ): CorpusRegistrationFactory {
        return new CorpusRegistrationFactory(
            strategies: $container->get(StrategyFactory::class),
            routes    : $container->get(RouteTable::class),
        );
    }
}
