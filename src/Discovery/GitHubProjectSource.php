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

namespace CommonDoc\Aggregate\Discovery;

use InvalidArgumentException;
use Override;

use function array_merge;
use function count;
use function is_array;
use function is_string;
use function rawurlencode;
use function sprintf;

/**
 * Discovers the onboarded repositories of one GitHub organization.
 *
 * Two endpoints, and no vendor SDK: the organization's repositories, then each repository's custom
 * property values. A repository is onboarded when its `doc-target` property carries a directory, so
 * the property is the whole onboarding cost and a repository without it is simply not listed.
 *
 * A failure here is not a project defect: nothing was read, so nothing can be reported per project.
 * Every failure names a reason code and stops the run before rendering.
 */
final readonly class GitHubProjectSource implements ProjectSourceInterface
{
    public const string DEFAULT_ENDPOINT = 'https://api.github.com';

    private const int PER_PAGE = 100;

    /**
     * @param non-empty-string $organization
     * @param non-empty-string $endpoint
     */
    public function __construct(
        private GitHubApi $api,
        private string $organization,
        private string $endpoint = self::DEFAULT_ENDPOINT,
    ) {}

    /**
     * @return list<Project>
     *
     * @throws DiscoveryFailed When no token is configured or the boundary refuses the run
     * @throws InvalidArgumentException When a repository name does not derive a site path
     */
    #[Override]
    public function all(): array
    {
        $projects = [];

        foreach ($this->repositories() as $repository) {
            $project = $this->project($repository);

            if (null !== $project) {
                $projects[] = $project;
            }
        }

        return $projects;
    }

    /**
     * A repository nobody onboarded answers the properties endpoint with a 404, which is an answer
     * rather than a failure, so it is read as "no property" and the repository is not listed.
     *
     * @param array<string, mixed> $repository
     *
     * @throws DiscoveryFailed When the boundary is unreachable or refuses the token
     */
    private function docTarget(array $repository): ?string
    {
        $properties = $this->api->find(
            sprintf(
                '%s/repos/%s/%s/properties/values',
                $this->endpoint,
                rawurlencode($this->owner($repository)),
                rawurlencode($this->requiredString($repository, 'name')),
            ),
        );

        if (null === $properties) {
            return null;
        }

        return DocTarget::from($properties);
    }

    /**
     * @param array<string, mixed> $repository
     *
     * @throws DiscoveryFailed When the repository carries no owner
     */
    private function owner(array $repository): string
    {
        /** @var mixed $owner */
        $owner = $repository['owner'] ?? null;

        if (! is_array($owner)) {
            throw DiscoveryFailed::unavailable(
                $this->endpoint,
                'a repository in the listing carries no owner.',
            );
        }

        /** @var array<string, mixed> $owner */
        return $this->requiredString($owner, 'login');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws DiscoveryFailed When the page is unreachable or refuses the token
     */
    private function page(int $page): array
    {
        return $this->api->get(
            sprintf(
                '%s/orgs/%s/repos?per_page=%d&page=%d',
                $this->endpoint,
                rawurlencode($this->organization),
                self::PER_PAGE,
                $page,
            ),
        );
    }

    /**
     * @param array<string, mixed> $repository
     *
     * @throws DiscoveryFailed When the listing entry names no repository
     * @throws InvalidArgumentException When the repository name does not derive a site path
     */
    private function project(array $repository): ?Project
    {
        $directory = $this->docTarget($repository);

        if (null === $directory) {
            return null;
        }

        $name = $this->requiredString($repository, 'name');

        return new Project(
            name        : $name,
            owner       : $this->owner($repository),
            sitePath    : SitePath::derive($name)->value,
            docDirectory: $directory,
        );
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws DiscoveryFailed When a page of the listing cannot be read
     */
    private function repositories(): array
    {
        $pages = [];
        $page  = 1;

        do {
            $batch   = $this->page($page);
            $pages[] = $batch;
            $page++;
        } while (count($batch) === self::PER_PAGE);

        return array_merge(...$pages);
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws DiscoveryFailed When the key is absent or is not a string
     */
    private function requiredString(array $object, string $key): string
    {
        /** @var mixed $value */
        $value = $object[$key] ?? null;

        if (! is_string($value) || '' === $value) {
            throw DiscoveryFailed::unavailable(
                $this->endpoint,
                sprintf('a repository in the listing carries no "%s".', $key),
            );
        }

        return $value;
    }
}
