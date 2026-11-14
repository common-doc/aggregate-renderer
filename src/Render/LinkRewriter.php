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
use CommonDoc\Aggregate\Build\LinkTotals;
use CommonDoc\Aggregate\Navigation\DocumentRouteProvider;
use CommonDoc\Aggregate\Output\RelativePath;
use CommonDoc\Aggregate\Version\Target;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Mezzio\Router\Exception\RuntimeException as RouterRuntimeException;
use Mezzio\Router\RouterInterface;

use function array_pop;
use function dirname;
use function explode;
use function implode;
use function iterator_to_array;
use function preg_match;
use function rawurldecode;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;

use const LIBXML_HTML_NOIMPLIED;
use const LIBXML_HTML_NODEFDTD;
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
        private LinkTotals $links,
    ) {}

    /**
     * @param string $html     The rendered page
     * @param Target $target   The project and version the page belongs to
     * @param string $fromPath The page's own document path, for relative resolution
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
            if (! $anchor instanceof DOMElement) {
                continue;
            }

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

    /**
     * The published href for one markdown link, or null when the link is not ours to rewrite.
     *
     * The order is the decision: a fragment, an address with a scheme and a root absolute address are
     * each already what they have to be, and only what is left can name a document of this corpus. A
     * miss is recorded rather than thrown, because one broken href must not cost the page it is on.
     */
    private function resolve(string $href, Target $target, string $fromPath): ?string
    {
        if ('' === $href) {
            $this->links->add(LinkOutcome::NonDocument);

            return null;
        }

        if (str_starts_with($href, '#')) {
            $this->links->add(LinkOutcome::Anchors);

            return null;
        }

        if (str_starts_with($href, '//') || 1 === preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*:~', $href)) {
            $this->links->add(LinkOutcome::External);

            return null;
        }

        if (str_starts_with($href, '/')) {
            $this->links->add(LinkOutcome::Absolute);

            return null;
        }

        $fragment = '';
        $hash     = strpos($href, '#');

        if (false !== $hash) {
            $fragment = substr($href, $hash);
            $href     = substr($href, 0, $hash);
        }

        $query        = '';
        $questionMark = strpos($href, '?');

        if (false !== $questionMark) {
            $query = substr($href, $questionMark);
            $href  = substr($href, 0, $questionMark);
        }

        $documentPath = $this->documentPathOf(
            fromPath: $fromPath,
            href    : rawurldecode($href),
        );

        if (null === $documentPath) {
            $this->links->add(LinkOutcome::NonDocument);

            return null;
        }

        try {
            $uri = $this->router->generateUri(DocumentRouteProvider::nameFor($target, $documentPath));
        } catch (RouterRuntimeException) {
            $this->links->add(LinkOutcome::Unresolved);

            return null;
        }

        $this->links->add(LinkOutcome::Documents);

        return $uri . $query . $fragment;
    }

    /**
     * The document path a relative href names, relative to the version root, or null when it names
     * none.
     *
     * Only a markdown path can name a document, so anything else is left alone. The rest is collapsed
     * against the page's own directory and then handed to `RelativePath`, the class that already
     * refuses a path leaving the tree: an href that escapes the version root is not a document of this
     * corpus and must not be rewritten into one.
     */
    private function documentPathOf(string $fromPath, string $href): ?string
    {
        if (! str_ends_with($href, '.md')) {
            return null;
        }

        $directory = dirname($fromPath);
        $segments  = [];

        foreach (explode('/', '.' === $directory ? $href : $directory . '/' . $href) as $segment) {
            if ('.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] === $segments) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $candidate = implode('/', $segments);

        try {
            return (new RelativePath($candidate))->value();
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
