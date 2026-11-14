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

namespace CommonDocTest\Aggregate;

use CommonDoc\Aggregate\Build\Container\SiteBuildFactoryFactory;
use CommonDoc\Aggregate\Build\SiteBuildFactory;
use CommonDoc\Aggregate\Build\SiteBuildFactoryInterface;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\ConfigProvider;
use CommonDoc\Aggregate\Console\BuildCommand;
use CommonDoc\Aggregate\Console\Container\BuildCommandFactory;
use CommonDoc\Aggregate\Discovery\Container\GitHubProjectSourceFactoryFactory;
use CommonDoc\Aggregate\Discovery\GitHubProjectSourceFactory;
use CommonDoc\Aggregate\Discovery\ProjectSourceFactoryInterface;
use CommonDoc\Aggregate\Http\Container\CurlHttpTransportFactory;
use CommonDoc\Aggregate\Http\Container\RequestFactoryFactory;
use CommonDoc\Aggregate\Http\CurlHttpTransport;
use CommonDoc\Aggregate\Render\SiteRendererFactory;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\Aggregate\Report\ReportFileFactory;
use CommonDoc\Aggregate\Report\ReportFileFactoryInterface;
use CommonDoc\Aggregate\Source\SourceFactory;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use Laminas\Diactoros\RequestFactory;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

use function array_replace_recursive;
use function dirname;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
#[CoversMethod(ConfigProvider::class, 'getViewConfig')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function assemblesTheCommandWithNoConsumerSuppliedHttpSeam(): void
    {
        $container = new ServiceManager(new ConfigProvider()->getDependencies());

        static::assertInstanceOf(BuildCommand::class, $container->get(BuildCommand::class));
        static::assertInstanceOf(CurlHttpTransport::class, $container->get(ClientInterface::class));
        static::assertInstanceOf(RequestFactory::class, $container->get(RequestFactoryInterface::class));
    }

    #[Test]
    public function contributesEveryMappedTemplateAsAnExistingFile(): void
    {
        foreach (new ConfigProvider()->getViewConfig()['map'] as $name => $path) {
            static::assertFileExists($path, $name);
        }
    }

    /**
     * The default has to be replaceable, so a consumer's own binding to the interface must win.
     */
    #[Test]
    public function letsAConsumerRegistrationReplaceTheDefaultClient(): void
    {
        $client = $this->createStub(ClientInterface::class);

        $container = new ServiceManager(array_replace_recursive(
            new ConfigProvider()->getDependencies(),
            [
                'factories' => [
                    ClientInterface::class => static fn(): ClientInterface => $client,
                ],
            ],
        ));

        static::assertSame($client, $container->get(ClientInterface::class));
        static::assertInstanceOf(BuildCommand::class, $container->get(BuildCommand::class));
    }

    #[Test]
    public function mergesDependenciesAndTemplatesWhenInvoked(): void
    {
        $provider = new ConfigProvider();

        static::assertSame(
            [
                'dependencies' => $provider->getDependencies(),
                'templates'    => $provider->getViewConfig(),
            ],
            $provider->__invoke(),
        );
    }

    #[Test]
    public function replacesTheDocumentAddressAndSuppliesTheLayout(): void
    {
        $views = new ConfigProvider()->getViewConfig();

        static::assertSame(ViewFactory::defaults(), $views['map']);
        static::assertSame('aggregate::layout', $views['layout']);
        static::assertSame(
            dirname(
                path  : __DIR__,
                levels: 2,
            )
                . '/templates/aggregate/document.phtml',
            $views['map'][ViewFactory::DOCUMENT],
        );
    }

    #[Test]
    public function returnsTheDependencyFactoryMap(): void
    {
        static::assertSame(
            [
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
            ],
            new ConfigProvider()->getDependencies(),
        );
    }
}
