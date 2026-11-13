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

namespace CommonDocTest\Aggregate\Render;

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Render\SiteRendererFactory;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Render\SiteRendererInterface;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Target;
use CommonDocTest\Aggregate\Support\Config;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteRendererFactory::class)]
#[CoversMethod(SiteRendererFactory::class, 'create')]
final class SiteRendererFactoryTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Test]
    public function buildsTheRendererTheBuildRendersThrough(): void
    {
        $factory = new SiteRendererFactory();

        static::assertInstanceOf(SiteRendererFactoryInterface::class, $factory);
        static::assertInstanceOf(SiteRendererInterface::class, $this->create());
    }

    #[Test]
    public function theRendererItBuildsRendersThroughTheGivenSource(): void
    {
        $renderer = $this->create(documents: ['index.md' => "# Home\n"]);

        $pages = $renderer->render(
            target: new Target(
                project: new Project(
                    name        : 'aggregate-renderer',
                    owner       : 'common-doc',
                    sitePath    : 'aggregate-renderer',
                    docDirectory: 'docs',
                ),
                version: '1.0',
            ),
            documents: ['index.md'],
        );

        static::assertCount(1, $pages);
        static::assertSame('https://docs.example.org/aggregate-renderer/1.0/', $pages[0]->address);
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    /**
     * @param array<string, string> $documents
     */
    private function create(array $documents = []): SiteRendererInterface
    {
        $source = $this->createStub(SourceInterface::class);
        $source
            ->method('contents')
            ->willReturnCallback(
                static fn(Target $target, string $documentPath): string => $documents[$documentPath] ?? '',
            );

        return new SiteRendererFactory()->create(
            config: Config::site(outputDirectory: "{$this->directory->path}/build"),
            source: $source,
        );
    }
}
