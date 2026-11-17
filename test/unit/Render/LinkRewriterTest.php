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

use CommonDoc\Aggregate\Build\LinkOutcome;
use CommonDoc\Aggregate\Build\LinkTotals;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\DocumentPath;
use CommonDoc\Aggregate\Render\LinkHref;
use CommonDoc\Aggregate\Render\LinkRewriter;
use CommonDocTest\Aggregate\Support\Targets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function preg_match;
use function sprintf;

#[CoversClass(LinkRewriter::class)]
#[CoversClass(DocumentPath::class)]
#[CoversClass(LinkHref::class)]
#[CoversClass(LinkTotals::class)]
#[CoversClass(RouteTable::class)]
#[CoversMethod(LinkRewriter::class, 'rewrite')]
#[CoversMethod(DocumentPath::class, 'resolve')]
#[CoversMethod(LinkHref::class, 'of')]
#[CoversMethod(LinkTotals::class, 'add')]
#[CoversMethod(LinkTotals::class, 'count')]
#[CoversMethod(LinkTotals::class, 'toArray')]
final class LinkRewriterTest extends TestCase
{
    private LinkRewriter $rewriter;

    private LinkTotals $links;

    #[Test]
    public function countsAnEmptyHrefAsANonDocumentLink(): void
    {
        static::assertSame('', $this->hrefOf('', fromPath: 'index.md'));
        static::assertSame(1, $this->links->count(LinkOutcome::NonDocument));
    }

    #[Test]
    public function countsAnImageWithoutRewritingIt(): void
    {
        $html = $this->rewriter->rewrite(
            html    : '<p><a href="flow-charts.md">flow charts</a><img src="./diagram.png" alt="diagram"></p>',
            target  : Targets::target(),
            fromPath: 'index.md',
        );

        static::assertStringContainsString('src="./diagram.png"', $html);
        static::assertStringContainsString('href="/aggregate-renderer/2.0/flow-charts.html"', $html);
        static::assertSame(1, $this->links->count(LinkOutcome::NonDocument));
        static::assertSame(1, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function keepsTheFragmentOfARewrittenLink(): void
    {
        static::assertSame(
            '/aggregate-renderer/2.0/guide/advanced.html#install',
            $this->hrefOf('advanced.md#install', fromPath: 'guide/setup.md'),
        );
    }

    #[Test]
    public function keepsTheQueryOfARewrittenLink(): void
    {
        static::assertSame(
            '/aggregate-renderer/2.0/guide/advanced.html?tab=2',
            $this->hrefOf('advanced.md?tab=2', fromPath: 'guide/setup.md'),
        );
    }

    #[Test]
    public function leavesAFragmentOfTheSamePageAlone(): void
    {
        static::assertSame('#install', $this->hrefOf('#install', fromPath: 'guide/setup.md'));
        static::assertSame(1, $this->links->count(LinkOutcome::Anchors));
        static::assertSame(0, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function leavesALinkThatEscapesTheVersionRootAlone(): void
    {
        static::assertSame('../escape.md', $this->hrefOf('../escape.md', fromPath: 'index.md'));
        static::assertSame(1, $this->links->count(LinkOutcome::Unresolved));
        static::assertSame(0, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function leavesALinkToAnUnpublishedDocumentAlone(): void
    {
        static::assertSame('missing.md', $this->hrefOf('missing.md', fromPath: 'guide/setup.md'));
        static::assertSame(1, $this->links->count(LinkOutcome::Unresolved));
        static::assertSame(0, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function leavesAMailAddressAlone(): void
    {
        static::assertSame(
            'mailto:docs@example.org',
            $this->hrefOf('mailto:docs@example.org', fromPath: 'guide/setup.md'),
        );
        static::assertSame(1, $this->links->count(LinkOutcome::External));
        static::assertSame(0, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function leavesAnExternalAddressAlone(): void
    {
        static::assertSame(
            'https://example.org/api.md',
            $this->hrefOf('https://example.org/api.md', fromPath: 'guide/setup.md'),
        );
        static::assertSame(1, $this->links->count(LinkOutcome::External));
        static::assertSame(0, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function leavesANonDocumentLinkAlone(): void
    {
        static::assertSame('images/diagram.png', $this->hrefOf('images/diagram.png', fromPath: 'guide/setup.md'));
        static::assertSame(1, $this->links->count(LinkOutcome::NonDocument));
        static::assertSame(0, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function leavesARootAbsoluteAddressAlone(): void
    {
        static::assertSame(
            '/elsewhere/index.html',
            $this->hrefOf('/elsewhere/index.html', fromPath: 'guide/setup.md'),
        );
        static::assertSame(1, $this->links->count(LinkOutcome::Absolute));
        static::assertSame(0, $this->links->count(LinkOutcome::Documents));
    }

    #[Test]
    public function returnsAPageCarryingOnlyImagesUntouched(): void
    {
        $html = '<p><img src="./diagram.png" alt="diagram"></p>';

        static::assertSame(
            $html,
            $this->rewriter->rewrite(
                html    : $html,
                target  : Targets::target(),
                fromPath: 'index.md',
            ),
        );
        static::assertSame(0, $this->links->count(LinkOutcome::NonDocument));
    }

    #[Test]
    public function returnsHtmlCarryingNoLinkUntouched(): void
    {
        static::assertSame(
            '<p>No link here.</p>' . "\n",
            $this->rewriter->rewrite(
                html    : '<p>No link here.</p>' . "\n",
                target  : Targets::target(),
                fromPath: 'guide/setup.md',
            ),
        );
        static::assertSame([], array_filter(
            $this->links->toArray(),
            static fn(int $count): bool => 0 !== $count,
        ));
    }

    #[Test]
    public function rewritesADocumentLinkInTheSameDirectory(): void
    {
        static::assertSame(
            '/aggregate-renderer/2.0/guide/advanced.html',
            $this->hrefOf('advanced.md', fromPath: 'guide/setup.md'),
        );
        static::assertSame(1, $this->links->count(LinkOutcome::Documents));
        static::assertSame(0, $this->links->count(LinkOutcome::Unresolved));
    }

    #[Test]
    public function rewritesADocumentLinkToAnotherDirectory(): void
    {
        static::assertSame(
            '/aggregate-renderer/2.0/flow-charts.html',
            $this->hrefOf('../flow-charts.md', fromPath: 'guide/setup.md'),
        );
    }

    #[Test]
    public function rewritesALinkToALandingPage(): void
    {
        static::assertSame(
            '/aggregate-renderer/2.0/guide/index.html',
            $this->hrefOf('index.md', fromPath: 'guide/setup.md'),
        );
        static::assertSame(
            '/aggregate-renderer/2.0/index.html',
            $this->hrefOf('../index.md', fromPath: 'guide/setup.md'),
        );
    }

    #[Test]
    public function rewritesALinkWrittenWithADotSegment(): void
    {
        static::assertSame(
            '/aggregate-renderer/2.0/flow-charts.html',
            $this->hrefOf('./flow-charts.md', fromPath: 'index.md'),
        );
        static::assertSame(1, $this->links->count(LinkOutcome::Documents));
    }

    protected function setUp(): void
    {
        $this->links = new LinkTotals();

        $this->rewriter = new LinkRewriter(
            router: Targets::routeTable(documents: [
                'index.md',
                'flow-charts.md',
                'guide/index.md',
                'guide/setup.md',
                'guide/advanced.md',
            ])->router,
            links : $this->links,
        );
    }

    private function hrefOf(string $href, string $fromPath): string
    {
        $html = $this->rewriter->rewrite(
            html    : sprintf('<p><a href="%s">link</a></p>', $href),
            target  : Targets::target(),
            fromPath: $fromPath,
        );

        static::assertSame(1, preg_match('~href="([^"]*)"~', $html, $matches));
        static::assertArrayHasKey(1, $matches);

        return $matches[1];
    }
}
