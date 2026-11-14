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

namespace CommonDocTest\Aggregate\Navigation;

use CommonDoc\Aggregate\Build\Corpus;
use CommonDoc\Aggregate\Build\ProjectCorpus;
use CommonDoc\Aggregate\Build\TargetCorpus;
use CommonDoc\Aggregate\Config\NavigationConfig;
use CommonDoc\Aggregate\Navigation\DocumentRouteProvider;
use CommonDoc\Aggregate\Navigation\DocumentRouteProviderFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDocTest\Aggregate\Support\Targets;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\Exception\RuntimeException as RouterRuntimeException;
use Mezzio\Router\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(DocumentRouteProvider::class)]
#[CoversClass(DocumentRouteProviderFactory::class)]
#[CoversClass(Corpus::class)]
#[CoversClass(ProjectCorpus::class)]
#[CoversClass(TargetCorpus::class)]
#[CoversMethod(DocumentRouteProvider::class, 'nameFor')]
#[CoversMethod(DocumentRouteProvider::class, 'registerRoutes')]
#[CoversMethod(DocumentRouteProviderFactory::class, 'create')]
final class DocumentRouteProviderTest extends TestCase
{
    #[Test]
    public function aNameTheRouterNeverReceivedHasNoUri(): void
    {
        $table = Targets::routeTable(documents: ['index.md']);

        $this->expectException(RouterRuntimeException::class);

        $table->router->generateUri(DocumentRouteProvider::nameFor(Targets::target(), 'unpublished.md'));
    }

    #[Test]
    public function carriesTheNavigationMetadataTheSidebarReads(): void
    {
        $options = $this->optionsOf(Targets::routeTable(documents: [
            'index.md',
            'api-reference/index.md',
        ]))['p|aggregate-renderer|2.0|api-reference|index'];

        static::assertSame(['docs'], $options['navigation']);
        static::assertSame('Api Reference', $options['label']);
        static::assertSame('aggregate-renderer', $options['project']);
        static::assertSame('2.0', $options['version']);
        static::assertSame('p|aggregate-renderer|2.0|index', $options['parent']);
        static::assertSame(0, $options['order']);
    }

    #[Test]
    public function derivesANameFromTheTargetAndTheDocumentPath(): void
    {
        $target = Targets::target();

        static::assertSame(
            'p|aggregate-renderer|2.0|index',
            DocumentRouteProvider::nameFor($target, 'index.md'),
        );
        static::assertSame(
            'p|aggregate-renderer|2.0|flow-charts',
            DocumentRouteProvider::nameFor($target, 'flow-charts.md'),
        );
        static::assertSame(
            'p|aggregate-renderer|2.0|api-reference|index',
            DocumentRouteProvider::nameFor($target, 'api-reference/index.md'),
        );
        static::assertSame(
            DocumentRouteProvider::nameFor($target, 'flow-charts.md'),
            DocumentRouteProvider::nameFor($target, 'flow-charts.md'),
        );
    }

    #[Test]
    public function keepsADottedDocumentApartFromADirectoryOfTheSameName(): void
    {
        $target = Targets::target();

        static::assertSame(
            'p|aggregate-renderer|2.0|api.v2',
            DocumentRouteProvider::nameFor($target, 'api.v2.md'),
        );
        static::assertSame(
            'p|aggregate-renderer|2.0|api|v2',
            DocumentRouteProvider::nameFor($target, 'api/v2.md'),
        );
    }

    #[Test]
    public function nestsADocumentUnderTheLandingPageOfItsDirectory(): void
    {
        $options = $this->optionsOf(Targets::routeTable(documents: [
            'index.md',
            'api-reference/index.md',
            'api-reference/reference.md',
        ]));

        static::assertNull($options['p|aggregate-renderer|2.0|index']['parent']);
        static::assertSame(
            'p|aggregate-renderer|2.0|index',
            $options['p|aggregate-renderer|2.0|api-reference|index']['parent'],
        );
        static::assertSame(
            'p|aggregate-renderer|2.0|api-reference|index',
            $options['p|aggregate-renderer|2.0|api-reference|reference']['parent'],
        );
    }

    #[Test]
    public function registersNothingWhenNavigationIsDisabled(): void
    {
        $factory = new DocumentRouteProviderFactory(
            addresses : new PageAddressFactory(hostname: Targets::HOSTNAME),
            navigation: new NavigationConfig(),
        );

        static::assertNull($factory->create(Targets::corpus(documents: ['index.md'])));
    }

    #[Test]
    public function registersOneRoutePerDocumentInSortedOrder(): void
    {
        $routes = Targets::routeTable(documents: [
            'index.md',
            'guide/setup.md',
            'flow-charts.md',
        ])->collector->getRoutes();

        static::assertSame(
            [
                'p|aggregate-renderer|2.0|flow-charts',
                'p|aggregate-renderer|2.0|guide|setup',
                'p|aggregate-renderer|2.0|index',
            ],
            array_map(static fn(Route $route): string => $route->getName(), $routes),
        );
        static::assertSame(
            [
                '/aggregate-renderer/2.0/flow-charts.html',
                '/aggregate-renderer/2.0/guide/setup.html',
                '/aggregate-renderer/2.0/index.html',
            ],
            array_map(static fn(Route $route): string => $route->getPath(), $routes),
        );
    }

    #[Test]
    public function registersTheCorpusWhenNavigationIsEnabled(): void
    {
        $table   = RouteTable::create();
        $factory = new DocumentRouteProviderFactory(
            addresses : new PageAddressFactory(hostname: Targets::HOSTNAME),
            navigation: new NavigationConfig(
                enabled   : true,
                identifier: 'manual',
            ),
        );

        $provider = $factory->create(Targets::corpus(documents: ['index.md']));

        static::assertInstanceOf(DocumentRouteProvider::class, $provider);

        $provider->registerRoutes($table->collector);

        static::assertSame(
            '/aggregate-renderer/2.0/index.html',
            $table->router->generateUri('p|aggregate-renderer|2.0|index'),
        );
        static::assertSame(
            ['manual'],
            $this->optionsOf($table)['p|aggregate-renderer|2.0|index']['navigation'],
        );
    }

    #[Test]
    public function rejectsASecondDocumentThatPublishesToOneAddress(): void
    {
        $table = Targets::routeTable(documents: ['index.md']);

        $this->expectException(DuplicateRouteException::class);

        new DocumentRouteProvider(
            addresses: new PageAddressFactory(hostname: Targets::HOSTNAME),
            corpus   : Targets::corpus(documents: ['index.md']),
        )->registerRoutes($table->collector);
    }

    /**
     * @return array<string, array<array-key, mixed>>
     */
    private function optionsOf(RouteTable $table): array
    {
        $options = [];

        foreach ($table->collector->getRoutes() as $route) {
            $options[$route->getName()] = $route->getOptions();
        }

        return $options;
    }
}
