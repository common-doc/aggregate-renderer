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
use CommonDoc\Aggregate\Output\CnameFile;
use RuntimeException;

use function sprintf;

/**
 * The site address file a build must leave behind for the host to serve.
 *
 * Its absence is a build failure rather than a warning, because publishing without it silently drops
 * the custom hostname while every address in the report still claims that hostname. The reason code
 * travels with the exception so the report and the process exit code cannot disagree about why the
 * build stopped.
 */
final class MissingCnameException extends RuntimeException
{
    private function __construct(
        public readonly ReasonCode $reasonCode,
        string $reason,
    ) {
        parent::__construct($reason);
    }

    public static function absent(): self
    {
        return new self(ReasonCode::MissingCname, sprintf('The site address file "%s" is absent.', CnameFile::NAME));
    }

    public static function mismatched(string $expected, string $actual): self
    {
        return new self(ReasonCode::MissingCname, sprintf(
            'The site address file "%s" holds "%s" rather than "%s".',
            CnameFile::NAME,
            $actual,
            $expected,
        ));
    }
}
