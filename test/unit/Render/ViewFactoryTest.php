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

use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\View\PageContext;
use CommonDocTest\Aggregate\Support\Config;
use Laminas\View\Renderer\PhpRenderer;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewFactory::class)]
#[CoversMethod(ViewFactory::class, 'create')]
#[CoversMethod(ViewFactory::class, 'defaults')]
#[CoversMethod(ViewFactory::class, 'environment')]
#[CoversMethod(ViewFactory::class, 'map')]
#[CoversMethod(ViewFactory::class, 'renderer')]
final class ViewFactoryTest extends TestCase
{
    #[Test]
    public function anEntryUnderAnEmptyNameIsNotAnAddressAndIsDropped(): void
    {
        $map = new ViewFactory()->map(config: Config::site(
            templateMap: ['' => '/srv/document.phtml'],
        ));

        static::assertSame(ViewFactory::defaults(), $map);
    }

    #[Test]
    public function anUnmappedSiteRendersThroughTheShippedDefaults(): void
    {
        static::assertSame(
            ViewFactory::defaults(),
            new ViewFactory()->map(config: Config::site()),
        );
    }

    #[Test]
    public function aSiteOverrideReplacesOnlyItsOwnEntry(): void
    {
        $map = new ViewFactory()->map(config: Config::site(
            templateMap: [ViewFactory::DOCUMENT => '/srv/document.phtml'],
        ));

        static::assertSame('/srv/document.phtml', $map[ViewFactory::DOCUMENT]);
        static::assertSame(
            ViewFactory::defaults()[ViewFactory::LAYOUT],
            $map[ViewFactory::LAYOUT],
        );
    }

    #[Test]
    public function createsATemplateRendererForTheShippedLayout(): void
    {
        static::assertInstanceOf(
            TemplateRendererInterface::class,
            new ViewFactory()->create(config: Config::site()),
        );
    }

    #[Test]
    public function rendersADocumentThroughTheDocumentTemplate(): void
    {
        $views       = new ViewFactory();
        $templates   = $views->create(config: Config::site());
        $environment = $views->environment(
            templates: $templates,
            pages    : new PageContext(),
        );

        $html = new HtmlRenderer(environment: $environment)->renderDocument(
            document: new MarkdownParser(environment: $environment)->parse(
                input: "# Title\n\nBody\n",
            ),
        );

        $markup = (string) $html;

        static::assertStringContainsString('<main class="document">', $markup);
        static::assertStringContainsString('<h1>Title</h1>', $markup);
        static::assertStringContainsString('Body', $markup);
    }

    #[Test]
    public function returnsAPhpRendererForTheSameMap(): void
    {
        static::assertInstanceOf(
            PhpRenderer::class,
            new ViewFactory()->renderer(config: Config::site()),
        );
    }

    #[Test]
    public function shipsADefaultForEveryAddressItRenders(): void
    {
        $defaults = ViewFactory::defaults();

        static::assertCount(4, $defaults);
        static::assertArrayHasKey(ViewFactory::DOCUMENT, $defaults);
        static::assertArrayHasKey(ViewFactory::LAYOUT, $defaults);
        static::assertArrayHasKey(ViewFactory::SITE_INDEX, $defaults);
        static::assertArrayHasKey(ViewFactory::NOT_FOUND, $defaults);

        foreach ($defaults as $name => $path) {
            static::assertFileExists($path, $name);
        }
    }

    #[Test]
    public function theDocumentAddressIsTheSharedOne(): void
    {
        static::assertSame('commondoc::document', ViewFactory::DOCUMENT);
    }
}
