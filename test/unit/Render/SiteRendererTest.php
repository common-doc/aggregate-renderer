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

use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Output\OutputTree;
use CommonDoc\Aggregate\Render\NotFoundPage;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Render\SiteIndex;
use CommonDoc\Aggregate\Render\SiteRenderer;
use CommonDoc\Aggregate\Render\SiteRendererFactory;
use CommonDoc\Aggregate\Render\SiteRendererInterface;
use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Target;
use CommonDocTest\Aggregate\Support\Config;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function is_file;

#[CoversClass(SiteRenderer::class)]
#[CoversClass(SiteIndex::class)]
#[CoversClass(NotFoundPage::class)]
#[CoversMethod(SiteRenderer::class, '__construct')]
#[CoversMethod(SiteRenderer::class, 'render')]
#[CoversMethod(SiteRenderer::class, 'writeNotFoundPage')]
#[CoversMethod(SiteRenderer::class, 'writeSiteIndex')]
#[CoversMethod(SiteIndex::class, '__construct')]
#[CoversMethod(SiteIndex::class, 'entries')]
#[CoversMethod(SiteIndex::class, 'write')]
#[CoversMethod(NotFoundPage::class, '__construct')]
#[CoversMethod(NotFoundPage::class, 'write')]
final class SiteRendererTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Test]
    public function listsEveryVersionOfAProjectUnderOneEntry(): void
    {
        $index = new SiteIndex(
            tree     : new OutputTree(root: $this->path('')),
            addresses: new PageAddressFactory(hostname: 'docs.example.org'),
            templates: new ViewFactory()->create(config: Config::site()),
        );

        static::assertSame(
            [
                [
                    'name'     => 'aggregate-renderer',
                    'versions' => [
                        [
                            'version' => '1.0',
                            'address' => 'https://docs.example.org/aggregate-renderer/1.0/',
                        ],
                        [
                            'version' => '1.1',
                            'address' => 'https://docs.example.org/aggregate-renderer/1.1/',
                        ],
                    ],
                ],
            ],
            $index->entries([$this->target(), $this->target(version: '1.1')]),
        );
    }

    #[Test]
    public function ordersTheResultsByAddress(): void
    {
        $pages = $this->renderer(documents: [
            'guide/setup.md' => "# Setup\n",
            'index.md'       => "# Home\n",
        ])->render(
            target: $this->target(),
            documents: ['guide/setup.md', 'index.md'],
        );

        static::assertCount(2, $pages);
        static::assertSame('https://docs.example.org/aggregate-renderer/1.0/', $pages[0]->address);
        static::assertSame(
            'https://docs.example.org/aggregate-renderer/1.0/guide/setup/',
            $pages[1]->address,
        );
    }

    #[Test]
    public function rendersEveryDocumentOfATargetIntoItsVersionDirectory(): void
    {
        $pages = $this->renderer(documents: ['getting-started.md' => "# Getting started\n\nInstall it.\n"])
            ->render(
                target: $this->target(),
                documents: ['getting-started.md'],
            );

        static::assertCount(1, $pages);
        static::assertSame(
            'https://docs.example.org/aggregate-renderer/1.0/getting-started/',
            $pages[0]->address,
        );
        static::assertSame(PageStatus::Rendered, $pages[0]->status);

        $html = $this->written('aggregate-renderer/1.0/getting-started/index.html');

        static::assertStringContainsString('<!DOCTYPE html>', $html);
        static::assertStringContainsString('<h1>Getting started</h1>', $html);
        static::assertStringContainsString('Install it.', $html);
    }

    #[Test]
    public function reportsEveryDocumentAsFailedWhenOneCannotBeAddressed(): void
    {
        $pages = $this->renderer(documents: ['index.md' => "# Home\n"])
            ->render(
                target: $this->target(),
                documents: ['../escape.md', 'index.md'],
            );

        static::assertCount(2, $pages);

        foreach ($pages as $page) {
            static::assertSame(PageStatus::Failed, $page->status);
        }

        static::assertFalse(is_file($this->path('aggregate-renderer/1.0/index.html')));
    }

    #[Test]
    public function writesAnIndexListingEveryPublishedVersion(): void
    {
        $this->renderer()->writeSiteIndex(targets: [$this->target(), $this->target(version: '1.1')]);

        $html = $this->written('index.html');

        static::assertStringContainsString('aggregate-renderer', $html);
        static::assertStringContainsString('https://docs.example.org/aggregate-renderer/1.0/', $html);
        static::assertStringContainsString('https://docs.example.org/aggregate-renderer/1.1/', $html);
    }

    #[Test]
    public function writesTheNotFoundPageAtTheOutputRoot(): void
    {
        $this->renderer()->writeNotFoundPage();

        $html = $this->written('404.html');

        static::assertStringContainsString('<!DOCTYPE html>', $html);
        static::assertStringContainsString('Not found', $html);
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    private function path(string $relativePath): string
    {
        return "{$this->directory->path}/build/{$relativePath}";
    }

    /**
     * @param array<string, string> $documents
     */
    private function renderer(array $documents = []): SiteRendererInterface
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

    private function target(string $version = '1.0'): Target
    {
        return new Target(
            project: new Project(
                name        : 'aggregate-renderer',
                owner       : 'common-doc',
                sitePath    : 'aggregate-renderer',
                docDirectory: 'docs',
            ),
            version: $version,
        );
    }

    private function written(string $relativePath): string
    {
        return (string) file_get_contents($this->path($relativePath));
    }
}
