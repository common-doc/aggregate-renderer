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

use CommonDoc\Aggregate\Render\DocumentReader;
use CommonDoc\Aggregate\Render\DocumentRenderer;
use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\View\PageContext;
use CommonDocTest\Aggregate\Support\Config;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentRenderer::class)]
#[CoversMethod(DocumentRenderer::class, '__construct')]
#[CoversMethod(DocumentRenderer::class, 'render')]
final class DocumentRendererTest extends TestCase
{
    #[Test]
    public function rendersASourceThatUsesCrlfLineEndings(): void
    {
        static::assertStringContainsString(
            '<h1>Title</h1>',
            $this->renderer($this->environment())->render(markdown: "# Title\r\n\r\nBody\r\n"),
        );
    }

    #[Test]
    public function rendersTheDocumentTheReaderParsed(): void
    {
        $environment = $this->environment();

        $html = $this->renderer($environment)->render(markdown: "# Title\n\nBody\n");

        static::assertStringContainsString('<h1>Title</h1>', $html);
        static::assertStringContainsString('Body', $html);
    }

    private function environment(): EnvironmentInterface
    {
        $views = new ViewFactory();

        return $views->environment(
            templates: $views->create(config: Config::site()),
            pages    : new PageContext(),
        );
    }

    private function renderer(EnvironmentInterface $environment): DocumentRenderer
    {
        return new DocumentRenderer(
            reader: new DocumentReader(parser: new MarkdownParser(environment: $environment)),
            html  : new HtmlRenderer(environment: $environment),
        );
    }
}
