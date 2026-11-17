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

namespace CommonDoc\Aggregate\Render;

use CommonDoc\Aggregate\Build\LinkOutcome;
use CommonDoc\Aggregate\Build\LinkTotal;
use CommonDoc\Aggregate\Navigation\DocumentRouteProvider;
use CommonDoc\Aggregate\Version\Target;
use DOMDocument;
use Mezzio\Router\Exception\RuntimeException as RouterRuntimeException;
use Mezzio\Router\RouterInterface;

use function str_contains;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;
use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

/**
 * Rewrites prose links to the address the page they name was actually published at.
 *
 * A document is written as markdown and links to another document with a markdown path, because that
 * is what the reader of the source has. Only the html is published, so every one of those links is
 * dead unless something translates it, and the translation has to come from the same table that
 * published the page: this class derives a route name and asks the router for the href, so a link can
 * only be rewritten to an address that exists.
 *
 * That is also how a miss is decided. There is no second list of published documents to consult:
 * the router throws for a name it does not hold, and an href it cannot be resolved for is left
 * exactly as written, so a broken link stays visible in the published page instead of being rewritten
 * into a differently broken one.
 */
final readonly class LinkRewriter
{
    public function __construct(
        private RouterInterface $router,
        private LinkTotal $links,
    ) {}

    /**
     * @param string           $html     The rendered page
     * @param Target           $target   The project and version the page belongs to
     * @param non-empty-string $fromPath The page's own document path, for relative resolution
     */
    public function rewrite(string $html, Target $target, string $fromPath): string
    {
        if (! str_contains($html, 'href=')) {
            return $html;
        }

        $document = new DOMDocument();

        if (! $document->loadHTML(
            source : $html,
            options: LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING,
        )) {
            return $html;
        }

        $images = $document->getElementsByTagName('img')->count();

        for ($index = 0; $index < $images; $index++) {
            $this->links->add(LinkOutcome::NonDocument);
        }

        foreach ($document->getElementsByTagName('a') as $anchor) {
            $resolved = $this->resolve(
                href    : $anchor->getAttribute('href'),
                target  : $target,
                fromPath: $fromPath,
            );

            if (null !== $resolved) {
                $anchor->setAttribute('href', $resolved);
            }
        }

        $out = $document->saveHTML();

        return false === $out ? $html : $out;
    }

    private function counted(LinkOutcome $outcome): null
    {
        $this->links->add($outcome);

        return null;
    }

    /**
     * The href the router holds for a document, or null when the document was never published.
     *
     * The router is the existence check: it throws for a name it was never given, and that throw is a
     * miss rather than a failure, because one broken href must not fail the page it appears on.
     *
     * @param non-empty-string $documentPath
     */
    private function published(Target $target, string $documentPath): ?string
    {
        try {
            return $this->router->generateUri(DocumentRouteProvider::nameFor($target, $documentPath));
        } catch (RouterRuntimeException) {
            return null;
        }
    }

    /**
     * The published href for one markdown link, or null when the link is not ours to rewrite.
     *
     * A miss is recorded rather than thrown, because one broken href must not cost the page it is on.
     *
     * @param non-empty-string $fromPath
     */
    private function resolve(string $href, Target $target, string $fromPath): ?string
    {
        $link = LinkHref::of(
            href    : $href,
            fromPath: $fromPath,
        );

        if (null === $link->documentPath) {
            return $this->counted($link->outcome);
        }

        $uri = $this->published($target, $link->documentPath);

        if (null === $uri) {
            return $this->counted(LinkOutcome::Unresolved);
        }

        $this->links->add(LinkOutcome::Document);

        return $uri . $link->suffix;
    }
}
