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

namespace CommonDoc\Aggregate\Discovery\Container;

use CommonDoc\Aggregate\Discovery\GitHubProjectSourceFactory;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Builds the GitHub project source factory from the HTTP seams a consumer registered.
 *
 * The client is the one seam this package cannot supply: a PSR-18 implementation is a consumer
 * choice, so a missing registration is reported as a missing service when the command is built
 * rather than as a fatal error later. The request factory has an invokable default from
 * laminas-diactoros, a dependency this package already carries.
 */
final readonly class GitHubProjectSourceFactoryFactory implements FactoryInterface
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
    ): GitHubProjectSourceFactory {
        return new GitHubProjectSourceFactory(
            client  : $container->get(ClientInterface::class),
            requests: $container->get(RequestFactoryInterface::class),
        );
    }
}
