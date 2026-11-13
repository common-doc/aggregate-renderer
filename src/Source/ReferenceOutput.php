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
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

/**
 * The reference names in the output of `git ls-remote`.
 *
 * The head and tag prefixes are stripped here because the version strategies compare candidate names
 * against a version, and a version is never spelled `refs/tags/v1.0.0`. A line that is neither a
 * branch nor a tag is dropped rather than guessed at.
 */
final readonly class ReferenceOutput
{
    private const string BRANCH_PREFIX = 'refs/heads/';

    private const string TAG_PREFIX = 'refs/tags/';

    /**
     * @return list<non-empty-string> Names in the order git reported them
     */
    public static function names(string $listing): array
    {
        $names = [];

        foreach (explode("\n", $listing) as $line) {
            $tab = strpos(
                haystack: $line,
                needle  : "\t",
            );

            if (false === $tab) {
                continue;
            }

            $name = self::withoutPrefix(substr($line, $tab + 1));

            if ('' === $name) {
                continue;
            }

            $names[] = $name;
        }

        return $names;
    }

    private static function withoutPrefix(string $reference): string
    {
        foreach ([self::BRANCH_PREFIX, self::TAG_PREFIX] as $prefix) {
            if (str_starts_with($reference, $prefix)) {
                return substr($reference, strlen($prefix));
            }
        }

        return '';
    }
}
