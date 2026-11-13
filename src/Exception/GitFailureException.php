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

use RuntimeException;

use function implode;
use function rtrim;
use function sprintf;
use function substr;

/**
 * One git invocation that did not produce a usable answer.
 *
 * The message carries the argument list and nothing from the environment, because the environment is
 * where a credential would travel. Diagnostics are trimmed and truncated so a remote that echoes a
 * long page cannot turn a failure into an unreadable report.
 */
final class GitFailureException extends RuntimeException
{
    private const int DIAGNOSTIC_LIMIT = 400;

    private function __construct(string $reason)
    {
        parent::__construct($reason);
    }

    /**
     * @param list<non-empty-string> $arguments
     */
    public static function didNotRun(array $arguments, string $diagnostic): self
    {
        return new self(sprintf(
            'The git command "git %s" could not be run: %s',
            implode(
                separator: ' ',
                array    : $arguments,
            ),
            self::diagnostic(diagnostic: $diagnostic),
        ));
    }

    /**
     * @param list<non-empty-string> $arguments
     */
    public static function failed(array $arguments, ?int $exitCode, string $diagnostic): self
    {
        return new self(sprintf(
            'The git command "git %s" exited with %s: %s',
            implode(
                separator: ' ',
                array    : $arguments,
            ),
            null === $exitCode ? 'no code' : (string) $exitCode,
            self::diagnostic(diagnostic: $diagnostic),
        ));
    }

    private static function diagnostic(string $diagnostic): string
    {
        $trimmed = rtrim(string: $diagnostic);

        if ('' === $trimmed) {
            return 'no diagnostic was reported.';
        }

        return substr(
            string: $trimmed,
            offset: 0,
            length: self::DIAGNOSTIC_LIMIT,
        );
    }
}
