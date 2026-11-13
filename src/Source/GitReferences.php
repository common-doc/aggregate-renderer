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

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\Target;

use function array_key_exists;
use function explode;
use function preg_match;
use function sprintf;
use function substr_count;

/**
 * The ref a version is read from, and the directory it is read out of.
 *
 * The version grammar decides the ref: `MAJOR.MINOR` is the branch `MAJOR.MINOR.x` and
 * `MAJOR.MINOR.PATCH` is the tag `vMAJOR.MINOR.PATCH`. A tree corpus has no per-version ref at all,
 * so every version is read from the default branch under its own directory, which is the whole of
 * the difference between the two strategies.
 */
final class GitReferences
{
    private const string REMOTE = 'refs/remotes/origin/';

    private const string SYMREF = 'ref: refs/heads/';

    /** @var array<non-empty-string, non-empty-string> Site path to the default branch as a local ref */
    private array $defaults = [];

    public function __construct(
        private readonly GitCheckout $checkouts,
    ) {}

    /**
     * @return non-empty-string
     *
     * @throws SourceUnavailable When the default branch cannot be determined
     */
    public function defaultRef(Project $project): string
    {
        if (array_key_exists($project->sitePath, $this->defaults)) {
            return $this->defaults[$project->sitePath];
        }

        $directory = $this->checkouts->directory($project);
        $listing   = $this->checkouts->run(
            project  : $project,
            directory: $directory,
            arguments: [
                'ls-remote',
                '--symref',
                $this->checkouts->urlOf($project),
                'HEAD',
            ],
        );

        foreach (explode("\n", $listing) as $line) {
            $matches = [];

            if (1 !== preg_match('~^ref: refs/heads/(?<branch>\S+)\t~', $line, $matches)) {
                continue;
            }

            $branch = $matches['branch'] ?? '';

            if ('' === $branch) {
                continue;
            }

            return $this->defaults[$project->sitePath] = sprintf('%s%s', self::REMOTE, $branch);
        }

        throw SourceUnavailable::for(
            project: $project,
            reason: sprintf('The default branch of "%s" could not be determined.', $project->sitePath),
        );
    }

    /**
     * @return non-empty-string
     *
     * @throws SourceUnavailable When a tree corpus needs the default branch and it is unavailable
     */
    public function refFor(Target $target, Strategy $strategy): string
    {
        if (Strategy::Tree === $strategy) {
            return $this->defaultRef($target->project);
        }

        return 2 === substr_count(
            haystack: $target->version,
            needle  : '.',
        )
            ? sprintf('refs/tags/v%s', $target->version)
            : sprintf('%s%s.x', self::REMOTE, $target->version);
    }

    /**
     * @return non-empty-string
     */
    public function rootFor(Target $target, Strategy $strategy): string
    {
        return Strategy::Tree === $strategy
            ? sprintf('%s/%s', $target->project->docDirectory, $target->version)
            : $target->project->docDirectory;
    }
}
