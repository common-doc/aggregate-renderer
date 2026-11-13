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
use CommonDoc\Aggregate\Exception\MissingDocDirectoryException;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Version\Strategy;

use function sprintf;

/**
 * The candidate names a project offers to the configured version strategy.
 *
 * The reference strategy asks the remote for its branches and tags. The tree strategy asks the
 * default branch for the immediate subdirectories of the documentation directory, because in a tree
 * corpus the directory name is the version.
 */
final class GitCandidates
{
    public function __construct(
        private readonly GitCheckout $checkouts,
        private readonly GitReferences $references,
        private readonly Strategy $strategy,
    ) {}

    /**
     * @return list<non-empty-string>
     *
     * @throws SourceUnavailableException   When the candidates cannot be listed
     * @throws MissingDocDirectoryException When the documentation directory is absent at the default branch
     */
    public function names(Project $project): array
    {
        return Strategy::Tree === $this->strategy
            ? $this->directoryNames($project)
            : $this->referenceNames($project);
    }

    /**
     * @return list<non-empty-string>
     *
     * @throws SourceUnavailableException   When the default branch cannot be listed
     * @throws MissingDocDirectoryException When the documentation directory is absent at the default branch
     */
    private function directoryNames(Project $project): array
    {
        $directory = $this->checkouts->directory($project);
        $ref       = $this->references->defaultRef($project);
        $root      = $project->docDirectory;
        $listing   = $this->checkouts->run(
            project  : $project,
            directory: $directory,
            arguments: [
                'ls-tree',
                '-d',
                '--name-only',
                $ref,
                '--',
                sprintf('%s/', $root),
            ],
        );

        $names = TreeOutput::directories(TreeOutput::paths($listing, $root));

        if ([] === $names) {
            throw MissingDocDirectoryException::at($project, $ref);
        }

        return $names;
    }

    /**
     * @return list<non-empty-string>
     *
     * @throws SourceUnavailableException When the remote cannot be listed
     */
    private function referenceNames(Project $project): array
    {
        $listing = $this->checkouts->run(
            project  : $project,
            directory: $this->checkouts->directory($project),
            arguments: [
                'ls-remote',
                '--heads',
                '--tags',
                '--refs',
                $this->checkouts->urlOf($project),
            ],
        );

        return ReferenceOutput::names($listing);
    }
}
