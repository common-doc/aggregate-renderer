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
use RuntimeException;

use function sprintf;

/**
 * A `site.json` that cannot be used as written.
 *
 * The reader names the key it was reading, so an operator is told which line to fix rather than
 * being handed a stack trace. It also carries the reason code the report records, so a caller never
 * has to deduce the vocabulary from the message. Configuration is read before anything is rendered,
 * so this always exits blocked and never produces a report.
 */
final class InvalidSiteConfigException extends RuntimeException
{
    private function __construct(
        public readonly string $key,
        public readonly ReasonCode $reasonCode,
        string $reason,
    ) {
        parent::__construct(sprintf('Invalid site configuration at "%s": %s', $key, $reason));
    }

    public static function at(string $key, string $reason): self
    {
        return new self($key, ReasonCode::InvalidSiteConfig, $reason);
    }
}
