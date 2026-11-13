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

use function in_array;
use function preg_match;
use function usort;

use Override;

/**
 * Resolves the version list from directory names inside the documentation directory.
 *
 * A directory named in version grammar is a version and its name is the version verbatim; every
 * other directory is content that is not a release line, so it is excluded.
 */
final readonly class TreeStrategy implements StrategyInterface
{
    private const string DIRECTORY_PATTERN = '~^(\d+\.\d+(?:\.\d+)?)$~';

    public function __construct(private Project $project) {}

    /**
     * @param list<non-empty-string> $candidates
     *
     * @return list<Target>
     *
     * @throws InvalidArgumentException When an accepted candidate does not produce a valid target
     */
    #[Override]
    public function resolve(array $candidates): array
    {
        $versions = [];

        foreach ($candidates as $candidate) {
            if (preg_match(self::DIRECTORY_PATTERN, $candidate) !== 1) {
                continue;
            }

            if (! in_array($candidate, $versions, true)) {
                $versions[] = $candidate;
            }
        }

        $targets = [];

        foreach ($versions as $version) {
            $targets[] = new Target($this->project, $version);
        }

        usort($targets, Target::compare(...));

        return $targets;
    }
}
