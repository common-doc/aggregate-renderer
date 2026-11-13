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

namespace CommonDoc\Aggregate\Console\Container;

use CommonDoc\Aggregate\Build\SiteBuildFactoryInterface;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\Console\BuildCommand;
use CommonDoc\Aggregate\Report\ReportFileFactoryInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Exception\ExceptionInterface;

/**
 * Builds the command from the configured factories.
 *
 * The command is handed the three factories rather than the three results, because none of the
 * results can exist before a command has read the configuration the operator pointed it at.
 */
final readonly class BuildCommandFactory implements FactoryInterface
{
    /**
     * @param ?array<mixed> $options
     *
     * @throws ContainerExceptionInterface When a service cannot be created
     * @throws NotFoundExceptionInterface  When a service is not registered
     * @throws ExceptionInterface          When the command refuses its name or an option
     */
    #[Override]
    public function __invoke(
        ContainerInterface $container,
        string $requestedName,
        ?array $options = null,
    ): BuildCommand {
        $reader  = $container->get(SiteConfigReader::class);
        $builds  = $container->get(SiteBuildFactoryInterface::class);
        $reports = $container->get(ReportFileFactoryInterface::class);

        return new BuildCommand(
            reader : $reader,
            builds : $builds,
            reports: $reports,
        );
    }
}
