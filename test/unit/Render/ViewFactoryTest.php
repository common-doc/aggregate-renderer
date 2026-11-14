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
use CommonDocTest\Aggregate\Support\MarkerDocumentRenderer;
use Laminas\View\Renderer\PhpRenderer;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Node\Block\Document;
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
    public function anEmptyNameDoesNotStopTheEntriesAfterItBeingResolved(): void
    {
        $map = new ViewFactory()->map(config: Config::site(
            templateMap: [
                ''                  => '/srv/ignored.phtml',
                'aggregate::custom' => '/srv/custom.phtml',
            ],
        ));

        static::assertArrayNotHasKey('', $map);
        static::assertSame('/srv/custom.phtml', $map['aggregate::custom']);
        static::assertCount(5, $map);
    }

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
    public function losesToADocumentRendererRegisteredAtAHigherPriority(): void
    {
        $environment = $this->environment();

        $environment->addRenderer(
            nodeClass: Document::class,
            renderer : new MarkerDocumentRenderer(markup: '<!-- competitor -->'),
            priority : 11,
        );

        $markup = $this->render(environment: $environment);

        static::assertStringContainsString('<!-- competitor -->', $markup);
        static::assertStringNotContainsString('<main class="document">', $markup);
    }

    #[Test]
    public function outranksADocumentRendererRegisteredLaterAtTheSamePriority(): void
    {
        $environment = $this->environment();

        $environment->addRenderer(
            nodeClass: Document::class,
            renderer : new MarkerDocumentRenderer(markup: '<!-- competitor -->'),
            priority : 10,
        );

        $markup = $this->render(environment: $environment);

        static::assertStringContainsString('<main class="document">', $markup);
        static::assertStringNotContainsString('<!-- competitor -->', $markup);
    }

    #[Test]
    public function rendersADocumentThroughTheDocumentTemplate(): void
    {
        $environment = $this->environment();
        $markup      = $this->render(environment: $environment);

        static::assertStringContainsString('<main class="document">', $markup);
        static::assertStringContainsString('<h1>Title</h1>', $markup);
        static::assertStringContainsString('Body', $markup);
    }

    #[Test]
    public function rendersATableAsTableMarkup(): void
    {
        $environment = $this->environment();

        $markup = (string) new HtmlRenderer(environment: $environment)->renderDocument(
            document: new MarkdownParser(environment: $environment)->parse(
                input: "| Exception | Thrown when |\n|---|---|\n| `E` | Never |\n",
            ),
        );

        static::assertStringContainsString('<table>', $markup);
        static::assertStringContainsString('<th>Exception</th>', $markup);
        static::assertStringContainsString('<td><code>E</code></td>', $markup);
        static::assertStringNotContainsString('|---|---|', $markup);
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

    private function environment(): EnvironmentInterface
    {
        $views = new ViewFactory();

        return $views->environment(
            templates: $views->create(config: Config::site()),
            pages    : new PageContext(),
        );
    }

    private function render(EnvironmentInterface $environment): string
    {
        return (string) new HtmlRenderer(environment: $environment)->renderDocument(
            document: new MarkdownParser(environment: $environment)->parse(
                input: "# Title\n\nBody\n",
            ),
        );
    }
}
