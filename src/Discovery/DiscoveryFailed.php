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

use CommonDoc\Aggregate\Build\ReasonCode;
use RuntimeException;
use Throwable;

use function sprintf;

/**
 * Discovery could not produce a project list at all.
 *
 * This is the "blocked before rendering" family: no project was read, so there is nothing a report
 * could describe and the run stops with a diagnostic instead. The reason code travels with the
 * exception so the exit code and the message cannot disagree about why.
 *
 * Nothing in a message or a previous exception carries the token itself. The environment variable
 * name is printable; the value never is.
 */
final class DiscoveryFailed extends RuntimeException
{
    private function __construct(
        public readonly ReasonCode $reasonCode,
        string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct($reason, previous: $previous);
    }

    public static function missingToken(string $envVar): self
    {
        return new self(
            ReasonCode::MissingToken,
            sprintf(
                'The environment variable "%s" is unset or empty, so no organization token is available.',
                $envVar,
            ),
        );
    }

    public static function rejectedToken(string $boundary): self
    {
        return new self(
            ReasonCode::RejectedToken,
            sprintf('The credential was rejected by "%s".', $boundary),
        );
    }

    public static function unavailable(string $boundary, string $detail, ?Throwable $previous = null): self
    {
        return new self(
            ReasonCode::DiscoveryUnavailable,
            sprintf('The boundary "%s" is unavailable: %s', $boundary, $detail),
            $previous,
        );
    }
}
