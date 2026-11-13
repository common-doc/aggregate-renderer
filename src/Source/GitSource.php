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
use CommonDoc\Aggregate\Version\Target;
use Override;

/**
 * Sources read from a git checkout of each project.
 *
 * This class is the seam and nothing else: it names the three questions of the interface and leaves
 * the checkout, the ref arithmetic and the parsing to the collaborators. Keeping it that thin is
 * what lets a second source implementation reuse the parts it wants without inheriting git.
 */
final readonly class GitSource implements SourceInterface
{
    public function __construct(
        private GitCandidates $candidates,
        private GitDocuments $documents,
    ) {}

    /**
     * @return list<non-empty-string>
     *
     * @throws SourceUnavailableException   When the candidates cannot be listed
     * @throws MissingDocDirectoryException When the documentation directory is absent at the default branch
     */
    #[Override]
    public function candidates(Project $project): array
    {
        return $this->candidates->names($project);
    }

    /**
     * @throws SourceUnavailableException When the document cannot be read
     */
    #[Override]
    public function contents(Target $target, string $documentPath): string
    {
        return $this->documents->contents(
            target      : $target,
            documentPath: $documentPath,
        );
    }

    /**
     * @return list<non-empty-string>
     *
     * @throws SourceUnavailableException   When the sources cannot be read
     * @throws MissingDocDirectoryException When the declared documentation directory is absent at the ref
     */
    #[Override]
    public function documents(Target $target): array
    {
        return $this->documents->paths($target);
    }
}
