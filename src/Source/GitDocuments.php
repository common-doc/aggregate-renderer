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

use CommonDoc\Aggregate\Exception\MissingDocDirectoryException;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\Target;

use function sprintf;

/**
 * The documents of one target, read straight out of the checkout.
 *
 * Both reads name a ref rather than a branch tip, so a build that runs while the repository moves
 * reads one commit consistently rather than a mixture of two.
 */
final class GitDocuments
{
    public function __construct(
        private readonly GitCheckout $checkouts,
        private readonly GitReferences $references,
        private readonly Strategy $strategy,
    ) {}

    /**
     * @param non-empty-string $documentPath A path previously returned by {@see self::paths()}
     *
     * @throws SourceUnavailableException When the document cannot be read
     */
    public function contents(Target $target, string $documentPath): string
    {
        $project   = $target->project;
        $directory = $this->checkouts->directory($project);
        $ref       = $this->references->refFor(
            target  : $target,
            strategy: $this->strategy,
        );
        $root = $this->references->rootFor(
            target  : $target,
            strategy: $this->strategy,
        );

        return $this->checkouts->run(
            project  : $project,
            directory: $directory,
            arguments: [
                'show',
                sprintf('%s:%s/%s', $ref, $root, $documentPath),
            ],
        );
    }

    /**
     * @return list<non-empty-string> Paths relative to the target's source root
     *
     * @throws SourceUnavailableException   When the tree cannot be listed
     * @throws MissingDocDirectoryException When the source root is absent at the ref
     */
    public function paths(Target $target): array
    {
        $project   = $target->project;
        $directory = $this->checkouts->directory($project);
        $ref       = $this->references->refFor(
            target  : $target,
            strategy: $this->strategy,
        );
        $root = $this->references->rootFor(
            target  : $target,
            strategy: $this->strategy,
        );
        $listing = $this->checkouts->run(
            project  : $project,
            directory: $directory,
            arguments: [
                'ls-tree',
                '-r',
                '--name-only',
                $ref,
                '--',
                sprintf('%s/', $root),
            ],
        );

        $paths = TreeOutput::documents(TreeOutput::paths($listing, $root));

        if ([] === $paths) {
            throw MissingDocDirectoryException::at($project, $ref);
        }

        return $paths;
    }
}
