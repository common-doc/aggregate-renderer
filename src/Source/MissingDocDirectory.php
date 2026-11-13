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

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Discovery\Project;
use RuntimeException;

use function sprintf;

/**
 * A project that is onboarded but carries nothing at the declared documentation directory.
 *
 * This is a defect in the project rather than in the build, so it carries its own reason code: the
 * repository custom property names a directory that the resolved ref does not have.
 */
final class MissingDocDirectory extends RuntimeException
{
    private function __construct(
        public readonly ReasonCode $reasonCode,
        string $reason,
    ) {
        parent::__construct($reason);
    }

    public static function at(Project $project, string $ref): self
    {
        return new self(ReasonCode::MissingDocDirectory, sprintf(
            'The documentation directory "%s" of "%s" is absent at "%s".',
            $project->docDirectory,
            $project->sitePath,
            $ref,
        ));
    }
}
