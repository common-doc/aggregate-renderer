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

namespace CommonDoc\Aggregate\Exception;

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Discovery\Project;
use RuntimeException;
use Throwable;

use function sprintf;

/**
 * A project whose sources could not be materialized or read.
 *
 * The reason code travels with the exception so the report and the process exit code cannot disagree
 * about why the project was skipped.
 */
final class SourceUnavailableException extends RuntimeException
{
    private function __construct(
        public readonly ReasonCode $reasonCode,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct($reason, previous: $previous);
    }

    public static function for(Project $project, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            ReasonCode::SourceUnavailable,
            sprintf('The sources of "%s" are unavailable: %s', $project->sitePath, $reason),
            $previous,
        );
    }
}
