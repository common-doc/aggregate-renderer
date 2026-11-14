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

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Navigation\DocumentRouteProviderFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use InvalidArgumentException;
use Override;

/**
 * Builds the corpus registration from the services the build shares.
 *
 * The corpus collector reads the source the caller already built, and the route provider needs the
 * address factory and the navigation setting of one configuration, so both are created here rather
 * than injected. The route table is the build's, because the provider has to register into the table
 * the rewriter and the sidebar read.
 */
final readonly class CorpusRegistrationFactory implements CorpusRegistrationFactoryInterface
{
    public function __construct(
        private StrategyFactory $strategies,
        private RouteTable $routes,
    ) {}

    /**
     * @throws InvalidArgumentException When the configured host name cannot address a page
     */
    #[Override]
    public function create(SiteConfig $config, SourceInterface $source): CorpusRegistrationInterface
    {
        return new CorpusRegistration(
            corpus   : new CorpusCollector(
                source    : $source,
                strategies: $this->strategies,
            ),
            providers: new DocumentRouteProviderFactory(
                addresses : new PageAddressFactory(hostname: $config->hostname),
                navigation: $config->navigation,
            ),
            routes   : $this->routes,
        );
    }
}
