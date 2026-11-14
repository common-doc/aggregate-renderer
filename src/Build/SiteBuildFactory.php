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
use CommonDoc\Aggregate\Discovery\ProjectSourceFactoryInterface;
use CommonDoc\Aggregate\Navigation\DocumentRouteProviderFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use InvalidArgumentException;
use League\CommonMark\Exception\AlreadyInitializedException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use Override;

/**
 * Assembles the build one site configuration describes.
 *
 * The source is built once here and handed to both the renderer and the corpus collector, so a project
 * is listed and then read against one checkout cache. Everything the build needs that does not come
 * from the configuration is injected, so this is the only class that knows the order in which the
 * pieces fit together. Discovery is the one seam that is itself a factory, because the organization
 * it lists belongs to the configuration and therefore cannot be known when the container is built.
 *
 * The corpus collector and the route provider are built here rather than registered, because both are
 * scoped to one configuration: one holds the source for this build, the other holds the addresses and
 * the navigation setting for it. Neither can be a shared service without one build's corpus leaking
 * into the next.
 */
final readonly class SiteBuildFactory implements SiteBuildFactoryInterface
{
    public function __construct(
        private ProjectSourceFactoryInterface $projects,
        private SourceFactoryInterface $sources,
        private StrategyFactory $strategies,
        private SiteRendererFactoryInterface $renderers,
        private RouteTable $routes,
        private LinkTotals $links,
    ) {}

    /**
     * @throws AlreadyInitializedException When the markdown environment has already been configured
     * @throws InvalidArgumentException    When the configuration cannot produce a build
     * @throws TemplateExceptionInterface  When the template map or the helper manager refuses a name
     */
    #[Override]
    public function create(SiteConfig $config): SiteBuild
    {
        $source   = $this->sources->create(config: $config);
        $renderer = $this->renderers->create(
            config: $config,
            source: $source,
        );

        return new SiteBuild(
            discovery   : $this->projects->create(config: $config),
            corpus      : new CorpusCollector(
                source    : $source,
                strategies: $this->strategies,
            ),
            renderer    : $renderer,
            projectBuild: new ProjectBuild(
                renderer: $renderer,
                links   : $this->links,
            ),
            providers   : new DocumentRouteProviderFactory(
                addresses : new PageAddressFactory(hostname: $config->hostname),
                navigation: $config->navigation,
            ),
            routes      : $this->routes,
            links       : $this->links,
        );
    }
}
