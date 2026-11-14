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

namespace CommonDoc\Aggregate;

use CommonDoc\Aggregate\Build\Container\SiteBuildFactoryFactory;
use CommonDoc\Aggregate\Build\SiteBuildFactory;
use CommonDoc\Aggregate\Build\SiteBuildFactoryInterface;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\Console\BuildCommand;
use CommonDoc\Aggregate\Console\Container\BuildCommandFactory;
use CommonDoc\Aggregate\Discovery\Container\GitHubProjectSourceFactoryFactory;
use CommonDoc\Aggregate\Discovery\GitHubProjectSourceFactory;
use CommonDoc\Aggregate\Discovery\ProjectSourceFactoryInterface;
use CommonDoc\Aggregate\Http\Container\CurlHttpTransportFactory;
use CommonDoc\Aggregate\Http\Container\RequestFactoryFactory;
use CommonDoc\Aggregate\Render\SiteRendererFactory;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\Aggregate\Report\ReportFileFactory;
use CommonDoc\Aggregate\Report\ReportFileFactoryInterface;
use CommonDoc\Aggregate\Source\SourceFactory;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Wiring entry point for the package.
 *
 * Declared under `extra.laminas.config-provider` in composer.json, so a consumer's
 * config aggregator merges this without any further registration.
 *
 * Everything registered here is either configuration-free or a factory that needs the configuration
 * to build its product, because the output tree, the git source and the renderer all depend on
 * values that only exist once `site.json` has been read. Discovery follows the same rule: the
 * organization it lists and the variable holding the token live in the configuration, so the
 * container supplies the HTTP seams and {@see ProjectSourceFactoryInterface} turns them into a
 * source per build. Both seams carry a default registration here, so the shipped command assembles
 * with no consumer wiring: {@see \CommonDoc\Aggregate\Http\CurlHttpTransport} for the client, and
 * diactoros' request factory for the message factory. A consumer that registers either interface
 * itself replaces the default, which is how a different transport, or a test double, is installed.
 *
 * @internal
 */
final class ConfigProvider
{
    /**
     * Services the command needs, keyed so a consumer can replace any one of them.
     *
     * Each seam is an interface with an alias onto this package's class, and the alias target is
     * what carries the factory. Registering the interface straight against a class name would let
     * the container build that class as the service with no arguments, which is only correct for
     * the factories that genuinely hold no dependencies.
     *
     * The two HTTP seams are the exception: they are keyed straight on their interface, because an
     * alias forwards the read to the target name and would then shadow a consumer binding to the
     * interface itself.
     *
     * @return array{aliases: array<interface-string, class-string>, factories: array<class-string, class-string>}
     */
    public function getDependencies(): array
    {
        return [
            'aliases'   => [
                ProjectSourceFactoryInterface::class => GitHubProjectSourceFactory::class,
                ReportFileFactoryInterface::class    => ReportFileFactory::class,
                SiteBuildFactoryInterface::class     => SiteBuildFactory::class,
                SiteRendererFactoryInterface::class  => SiteRendererFactory::class,
                SourceFactoryInterface::class        => SourceFactory::class,
            ],
            'factories' => [
                BuildCommand::class               => BuildCommandFactory::class,
                ClientInterface::class            => CurlHttpTransportFactory::class,
                GitHubProjectSourceFactory::class => GitHubProjectSourceFactoryFactory::class,
                ReportFileFactory::class          => InvokableFactory::class,
                RequestFactoryInterface::class    => RequestFactoryFactory::class,
                SiteBuildFactory::class           => SiteBuildFactoryFactory::class,
                SiteConfigReader::class           => InvokableFactory::class,
                SiteRendererFactory::class        => InvokableFactory::class,
                SourceFactory::class              => InvokableFactory::class,
                StrategyFactory::class            => InvokableFactory::class,
            ],
        ];
    }

    /**
     * The template addresses this package ships.
     *
     * Contributed under `templates`, which merges with commondoc's own map: the document address is
     * replaced by this package's body template, and the layout is this package's shell, which owns
     * the doctype, the head and the site chrome. The map outranks the path stack, so a site that
     * leaves a name alone still renders through a shipped file.
     *
     * @return array{map: array<non-empty-string, non-empty-string>, layout: non-empty-string}
     */
    public function getViewConfig(): array
    {
        return [
            'map'    => ViewFactory::defaults(),
            'layout' => ViewFactory::LAYOUT,
        ];
    }

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getViewConfig(),
        ];
    }
}
