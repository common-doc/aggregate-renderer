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

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Discovery\Project;

/**
 * The ambiguity checks a corpus passes before a single page is written.
 *
 * Two projects that derive the same site path would be published into one directory, so one of them
 * would silently overwrite the other. That is an ambiguity in the corpus rather than a defect in a
 * project, which is why it stops the build instead of being recorded against a project and why the
 * answer is a reason code rather than an exception: the caller decides what a violation means for
 * the run, and this class performs no input, no output and no rendering of its own.
 *
 * @internal
 */
final readonly class CorpusCheck
{
    /**
     * The reason the corpus cannot be rendered, or null when it can.
     *
     * @param list<Project> $projects Ordered by site path, as discovery delivered it
     */
    public function violation(array $projects): ?ReasonCode
    {
        /** @var array<non-empty-string, true> $claimed */
        $claimed = [];

        foreach ($projects as $project) {
            if (true === ($claimed[$project->sitePath] ?? null)) {
                return ReasonCode::DuplicateSlug;
            }

            $claimed[$project->sitePath] = true;
        }

        return null;
    }
}
