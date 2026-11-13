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
use CommonDoc\Aggregate\Exception\GitFailureException;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use InvalidArgumentException;

use function array_key_exists;
use function implode;
use function is_dir;
use function mkdir;
use function rtrim;
use function sprintf;

/**
 * The one cache directory per project that every read of that project goes through.
 *
 * It is a bare repository, not a working tree: the build reads refs with `ls-tree` and `show`, so
 * nothing is ever checked out and a build that fails halfway cannot leave a half-materialized tree
 * behind. A second build of the same project reuses the directory and fetches into it.
 */
final class GitCheckout
{
    private const string SOURCES = 'sources';

    /** @var array<non-empty-string, non-empty-string> Site path to materialized checkout */
    private array $directories = [];

    /** @var non-empty-string */
    private string $cacheDirectory;

    /**
     * @param non-empty-string $cacheDirectory Absolute directory the checkouts are kept under
     * @param non-empty-string $remoteBase     Base URL the project repositories are cloned from
     *
     * @throws InvalidArgumentException When the cache directory is empty
     */
    public function __construct(
        private readonly GitRunnerInterface $runner,
        string $cacheDirectory,
        private readonly string $remoteBase = 'https://github.com',
    ) {
        $normalised = rtrim(
            string    : $cacheDirectory,
            characters: '/\\',
        );

        if ('' === $normalised) {
            throw new InvalidArgumentException('A git source requires a cache directory.');
        }

        $this->cacheDirectory = $normalised;
    }

    /**
     * @throws SourceUnavailableException When the checkout cannot be prepared
     */
    public function directory(Project $project): string
    {
        if (array_key_exists($project->sitePath, $this->directories)) {
            return $this->directories[$project->sitePath];
        }

        $directory = implode('/', [$this->cacheDirectory, self::SOURCES, $project->sitePath]);

        is_dir(filename: sprintf('%s/.git', $directory))
            ? $this->update($project, $directory)
            : $this->create($project, $directory);

        $this->run(
            project  : $project,
            directory: $directory,
            arguments: [
                'fetch',
                '--quiet',
                '--force',
                '--prune',
                '--depth=1',
                'origin',
                '+refs/heads/*:refs/remotes/origin/*',
                '+refs/tags/*:refs/tags/*',
            ],
        );

        return $this->directories[$project->sitePath] = $directory;
    }

    /**
     * Runs one git command in the checkout of a project.
     *
     * The failure is translated here and only here, so every caller reports the same way and no
     * caller has to know that a process was started at all.
     *
     * @param list<non-empty-string> $arguments
     *
     * @throws SourceUnavailableException When git cannot be started or exits with a non-zero code
     */
    public function run(Project $project, string $directory, array $arguments): string
    {
        try {
            return $this->runner->run(
                arguments       : $arguments,
                workingDirectory: $directory,
            );
        } catch (GitFailureException $failure) {
            throw SourceUnavailableException::for(
                project : $project,
                reason  : $failure->getMessage(),
                previous: $failure,
            );
        }
    }

    /**
     * @return non-empty-string
     */
    public function urlOf(Project $project): string
    {
        return sprintf(
            '%s/%s/%s.git',
            rtrim(
                string    : $this->remoteBase,
                characters: '/',
            ),
            $project->owner,
            $project->name,
        );
    }

    /**
     * @throws SourceUnavailableException When the cache directory cannot be created
     */
    private function create(Project $project, string $directory): void
    {
        if (
            ! is_dir($directory)
            && ! mkdir(
                directory  : $directory,
                permissions: 0o777,
                recursive  : true,
            )
            && ! is_dir($directory)
        ) {
            throw SourceUnavailableException::for(
                project: $project,
                reason: sprintf('The cache directory "%s" could not be created.', $directory),
            );
        }

        $this->run(
            project  : $project,
            directory: $directory,
            arguments: ['init', '--quiet'],
        );
        $this->run(
            project  : $project,
            directory: $directory,
            arguments: [
                'remote',
                'add',
                'origin',
                $this->urlOf($project),
            ],
        );
    }

    /**
     * @throws SourceUnavailableException When the remote cannot be repointed
     */
    private function update(Project $project, string $directory): void
    {
        $this->run(
            project  : $project,
            directory: $directory,
            arguments: [
                'remote',
                'set-url',
                'origin',
                $this->urlOf($project),
            ],
        );
    }
}
