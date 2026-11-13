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

namespace CommonDoc\Aggregate\Source;

use function explode;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The paths in the output of `git ls-tree`.
 *
 * Paths are made relative to the source root first, so everything downstream names a document the
 * same way it is addressed on the published site rather than the way the repository stores it.
 */
final readonly class TreeOutput
{
    private const string MARKDOWN = '.md';

    /**
     * @param list<non-empty-string> $paths
     *
     * @return list<non-empty-string> The immediate subdirectory names of the source root
     */
    public static function directories(array $paths): array
    {
        $directories = [];

        foreach ($paths as $path) {
            if (str_contains($path, '/')) {
                continue;
            }

            $directories[] = $path;
        }

        return $directories;
    }

    /**
     * @param list<non-empty-string> $paths
     *
     * @return list<non-empty-string> The markdown documents of the source root
     */
    public static function documents(array $paths): array
    {
        $documents = [];

        foreach ($paths as $path) {
            if (! str_ends_with($path, self::MARKDOWN)) {
                continue;
            }

            $documents[] = $path;
        }

        return $documents;
    }

    /**
     * @return list<non-empty-string> Paths relative to the source root, in the order git reported them
     */
    public static function paths(string $listing, string $root): array
    {
        $prefix = sprintf('%s/', $root);
        $paths  = [];

        foreach (explode("\n", $listing) as $line) {
            $path = rtrim(string: $line);

            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $relative = substr($path, strlen($prefix));

            if ('' === $relative) {
                continue;
            }

            $paths[] = $relative;
        }

        return $paths;
    }
}
