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
use Override;

use function in_array;
use function preg_match;
use function usort;

/**
 * Resolves the version list from repository reference names.
 *
 * Accepted are the two reference forms a maintainer writes by hand for a documentation line: the
 * release tag `vX.Y.Z` and the branch `X.Y.x`. Anything else is a reference that happens to exist
 * rather than a version, so it is excluded rather than repaired into something plausibly close.
 */
final readonly class RefStrategy implements StrategyInterface
{
    private const string TAG_PATTERN = '~^v(\d+\.\d+\.\d+)$~';

    private const string BRANCH_PATTERN = '~^(\d+\.\d+)\.x$~';

    public function __construct(
        private Project $project,
    ) {}

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
            $version = $this->versionOf($candidate);

            if (null === $version) {
                continue;
            }

            if (! in_array($version, $versions, strict: true)) {
                $versions[] = $version;
            }
        }

        $targets = [];

        foreach ($versions as $version) {
            $targets[] = new Target($this->project, $version);
        }

        usort($targets, Target::compare(...));

        return $targets;
    }

    /**
     * @return non-empty-string|null
     */
    private function versionOf(string $candidate): ?string
    {
        $matches = [];

        if (preg_match(self::TAG_PATTERN, $candidate, $matches) === 1) {
            $tag = $matches[1] ?? '';

            return '' === $tag ? null : $tag;
        }

        if (preg_match(self::BRANCH_PATTERN, $candidate, $matches) === 1) {
            $branch = $matches[1] ?? '';

            return '' === $branch ? null : $branch;
        }

        return null;
    }
}
