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
use InvalidArgumentException;

use function explode;
use function in_array;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function substr;

/**
 * Derives the output file and the public address of a page from one target and one document path.
 *
 * This is the only place in the build where a page's file or address is computed, which is what makes
 * the address mapping in `contracts/output-tree.md` structural. A document `index.md` at the root of a
 * version maps to that version's root, and every other document maps to a directory named after it, so
 * every published address is a directory address and every file is an `index.html`.
 */
final readonly class PageAddressFactory
{
    private const string DOCUMENT_PATTERN = '~^[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*\.md$~';

    private const string INDEX_SUFFIX = '/index';

    /** @var non-empty-string */
    private string $hostname;

    /**
     * @param string $hostname Bare host name, checked below rather than assumed
     *
     * @throws InvalidArgumentException When the host name cannot address a site
     */
    public function __construct(string $hostname)
    {
        if ('' === $hostname || str_contains($hostname, '/')) {
            throw new InvalidArgumentException(
                sprintf('The host name "%s" cannot address a site.', $hostname),
            );
        }

        $this->hostname = $hostname;
    }

    /**
     * The directory one document is served from, relative to the version root.
     *
     * @throws InvalidArgumentException When the path is not a relative markdown path below the directory
     */
    private static function directoryOf(string $documentPath): string
    {
        if (preg_match(self::DOCUMENT_PATTERN, $documentPath) !== 1) {
            throw new InvalidArgumentException(
                sprintf('The document path "%s" is not a relative markdown path.', $documentPath),
            );
        }

        if (in_array(
            needle  : '..',
            haystack: explode('/', $documentPath),
            strict  : true,
        )) {
            throw new InvalidArgumentException(
                sprintf('The document path "%s" leaves the documentation directory.', $documentPath),
            );
        }

        $withoutExtension = substr(
            string: $documentPath,
            offset: 0,
            length: -3,
        );

        if ('index' === $withoutExtension) {
            return '';
        }

        if (str_ends_with($withoutExtension, self::INDEX_SUFFIX)) {
            return substr(
                string: $withoutExtension,
                offset: 0,
                length: -6,
            );
        }

        return $withoutExtension;
    }

    /**
     * @param non-empty-string $documentPath Document path relative to the project's documentation directory
     *
     * @throws InvalidArgumentException When the document path cannot name a page
     */
    public function forDocument(Target $target, string $documentPath): PageAddress
    {
        $directory = self::directoryOf($documentPath);
        $prefix    = "{$target->project->sitePath}/{$target->version}";
        $file      = '' === $directory
            ? "{$prefix}/index.html"
            : "{$prefix}/{$directory}/index.html";
        $address = 'https://' . $this->hostname . '/' . $prefix . '/' . ('' === $directory ? '' : "{$directory}/");

        return new PageAddress(
            file   : $file,
            address: $address,
        );
    }
}
