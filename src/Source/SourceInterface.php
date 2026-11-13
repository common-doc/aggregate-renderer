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
use CommonDoc\Aggregate\Version\Target;

/**
 * The seam every source implementation sits behind.
 *
 * It answers three questions and nothing else: which candidate names exist for a project, which
 * documents one target holds, and what one document contains. Nothing about how they are obtained
 * crosses this boundary, which is what keeps version control, the network and the cache directory
 * out of the build and out of the renderer.
 *
 * @internal
 */
interface SourceInterface
{
    /**
     * The names the configured strategy chooses a version list from.
     *
     * @return list<non-empty-string> Names without a reference prefix, unordered and possibly duplicated
     *
     * @throws SourceUnavailable When the candidates cannot be listed
     */
    public function candidates(Project $project): array;

    /**
     * The source text of one document, exactly as the repository stores it.
     *
     * @param non-empty-string $documentPath A path previously returned by {@see self::documents()}
     *
     * @throws SourceUnavailable When the document cannot be read
     */
    public function contents(Target $target, string $documentPath): string;

    /**
     * The documents of one target.
     *
     * Paths are relative to the target's source root, which is the documentation directory itself
     * for a reference corpus and the version directory inside it for a tree corpus. Ordering is by
     * path, so two builds of one corpus produce the same page list.
     *
     * @return list<non-empty-string>
     *
     * @throws SourceUnavailable      When the sources cannot be read
     * @throws MissingDocDirectory    When the declared documentation directory is absent at the ref
     */
    public function documents(Target $target): array;
}
