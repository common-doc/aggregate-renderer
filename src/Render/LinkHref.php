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
use InvalidArgumentException;

use function preg_match;
use function rawurldecode;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;

/**
 * One anchor href, read into what the build can act on.
 *
 * A page links out in five ways that this build must not touch, and one way it must: a relative path
 * to a markdown document. Everything that decides which of those an href is lives here, so the
 * rewriter only has to ask the router, and a rule about what a link means is written once.
 *
 * `documentPath` is null when the href is not a document of this corpus, and `outcome` then says
 * which kind it was, because the build counts every link it sees. `suffix` carries the query and the
 * fragment, which have to survive the rewrite: they point into the document, not at it.
 */
final readonly class LinkHref
{
    private const string SCHEME_PATTERN = '~^[a-zA-Z][a-zA-Z0-9+.-]*:~';

    /**
     * @param non-empty-string|null $documentPath The markdown document the href names, once collapsed
     */
    private function __construct(
        public ?string $documentPath,
        public string $suffix,
        public LinkOutcome $outcome,
    ) {}

    /**
     * @param non-empty-string $fromPath The document path of the page that carries the link
     */
    public static function of(string $href, string $fromPath): self
    {
        $unchanged = self::unchanged($href);

        if (false !== $unchanged) {
            return new self(
                documentPath: null,
                suffix      : '',
                outcome     : $unchanged,
            );
        }

        $parts   = self::split($href);
        $decoded = rawurldecode($parts['path']);

        if (! str_ends_with($decoded, '.md')) {
            return new self(
                documentPath: null,
                suffix      : $parts['suffix'],
                outcome     : LinkOutcome::NonDocument,
            );
        }

        try {
            $documentPath = DocumentPath::resolve(
                fromPath: $fromPath,
                href    : $decoded,
            )->value;
        } catch (InvalidArgumentException) {
            return new self(
                documentPath: null,
                suffix      : $parts['suffix'],
                outcome     : LinkOutcome::Unresolved,
            );
        }

        return new self(
            documentPath: $documentPath,
            suffix      : $parts['suffix'],
            outcome     : LinkOutcome::Document,
        );
    }

    /**
     * The markdown path of an href, and the query and fragment that must survive the rewrite.
     *
     * @return array{path: string, suffix: string}
     */
    private static function split(string $href): array
    {
        $fragment = '';
        $hash     = strpos(
            haystack: $href,
            needle  : '#',
        );

        if (false !== $hash) {
            $fragment = substr(
                string: $href,
                offset: $hash,
            );
            $href = substr(
                string: $href,
                offset: 0,
                length: $hash,
            );
        }

        $query        = '';
        $questionMark = strpos(
            haystack: $href,
            needle  : '?',
        );

        if (false !== $questionMark) {
            $query = substr(
                string: $href,
                offset: $questionMark,
            );
            $href = substr(
                string: $href,
                offset: 0,
                length: $questionMark,
            );
        }

        return ['path' => $href, 'suffix' => $query . $fragment];
    }

    /**
     * The outcome for an href that is already what it has to be, or false when it may name a document.
     *
     * A fragment stays inside the page, an address with a scheme or a protocol relative host leaves
     * the site, and a root absolute address is already in published form: there is no inverse from an
     * address to a document, so it is counted and left alone rather than guessed at.
     */
    private static function unchanged(string $href): LinkOutcome|false
    {
        if ('' === $href) {
            return LinkOutcome::NonDocument;
        }

        if (str_starts_with($href, '#')) {
            return LinkOutcome::Anchor;
        }

        if (str_starts_with($href, '//') || 1 === preg_match(self::SCHEME_PATTERN, $href)) {
            return LinkOutcome::External;
        }

        return str_starts_with($href, '/') ? LinkOutcome::Absolute : false;
    }
}
