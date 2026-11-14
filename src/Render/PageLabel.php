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

use CommonDoc\Aggregate\Version\Target;

use function str_replace;
use function strrpos;
use function substr;
use function ucwords;

/**
 * The title a page is published under, derived from the document path rather than from its content.
 *
 * The derivation is part of the contract, not a convenience: a title read from the document would
 * change whenever the prose did, and the page address would not. It lives here, apart from the
 * renderer, because the sidebar label and the page title are the same value and must not be derived
 * twice.
 */
final class PageLabel
{
    /**
     * @param non-empty-string $documentPath Document path relative to the project's documentation directory
     */
    public static function for(Target $target, string $documentPath): string
    {
        $stem = substr(
            string: $documentPath,
            offset: 0,
            length: -3,
        );
        $slash = strrpos(
            haystack: $stem,
            needle  : '/',
        );
        $last = false === $slash
            ? $stem
            : substr(
                string: $stem,
                offset: $slash + 1,
            );

        if ('index' !== $last) {
            return self::humanise(stem: $last);
        }

        if (false === $slash) {
            return $target->project->name;
        }

        $parent = substr(
            string: $stem,
            offset: 0,
            length: $slash,
        );
        $parentSlash = strrpos(
            haystack: $parent,
            needle  : '/',
        );
        $entry = false === $parentSlash
            ? $parent
            : substr(
                string: $parent,
                offset: $parentSlash + 1,
            );

        return self::humanise(stem: $entry);
    }

    private static function humanise(string $stem): string
    {
        return ucwords(str_replace(
            search : ['-', '_'],
            replace: ' ',
            subject: $stem,
        ));
    }
}
