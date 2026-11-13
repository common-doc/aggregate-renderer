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

namespace CommonDoc\Aggregate\Version;

use CommonDoc\Aggregate\Discovery\Project;
use InvalidArgumentException;

use function count;
use function explode;
use function max;
use function preg_match;
use function sprintf;
use function strcmp;

/**
 * One project at one version.
 *
 * This is the only value from which an output file or a public address is derived, which is what
 * makes "one page, one file, one address" structural rather than conventional. It deliberately
 * carries neither: a stored path or address can go stale, a derived one cannot.
 */
final readonly class Target
{
    /** @var non-empty-string */
    public string $version;

    /**
     * @param non-empty-string $version
     *
     * @throws InvalidArgumentException When the version is not in the accepted grammar
     */
    public function __construct(
        public Project $project,
        string $version,
    ) {
        if (preg_match('/^\d+\.\d+(?:\.\d+)?$/', $version) !== 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Project "%s" declares the version "%s", which is neither MAJOR.MINOR nor MAJOR.MINOR.PATCH.',
                    $project->name,
                    $version,
                ),
            );
        }

        $this->version = $version;
    }

    /**
     * Ordering for one corpus: by site path, then by version compared segment by segment numerically.
     */
    public static function compare(self $left, self $right): int
    {
        $bySitePath = strcmp($left->project->sitePath, $right->project->sitePath);

        if ($bySitePath !== 0) {
            return $bySitePath;
        }

        return self::compareVersions($left->version, $right->version);
    }

    /**
     * Numeric comparison per segment, so `1.10.0` sorts after `1.9.0` rather than before it.
     */
    private static function compareVersions(string $left, string $right): int
    {
        $leftSegments  = explode('.', $left);
        $rightSegments = explode('.', $right);
        $segments      = max(count($leftSegments), count($rightSegments));

        for ($index = 0; $index < $segments; $index++) {
            $bySegment = (int) ($leftSegments[$index] ?? '0') <=> (int) ($rightSegments[$index] ?? '0');

            if ($bySegment !== 0) {
                return $bySegment;
            }
        }

        return strcmp($left, $right);
    }
}
