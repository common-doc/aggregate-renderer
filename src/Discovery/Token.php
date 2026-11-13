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

use CommonDoc\Aggregate\Exception\DiscoveryException;

use function getenv;
use function trim;

/**
 * The organization credential, named here and read from the environment when it is needed.
 *
 * Nothing holds the token itself: this value carries the name of the variable, so a token cannot
 * reach a configuration hash, a template, a diagnostic or the report by being passed around. Reading
 * the environment on every call rather than caching the value also means a rotated token takes effect
 * on the next request without restarting anything.
 */
final readonly class Token
{
    /**
     * @param non-empty-string $envVar
     */
    public function __construct(
        private string $envVar,
    ) {}

    /**
     * @return non-empty-string
     *
     * @throws DiscoveryException When the variable is unset or carries nothing
     */
    public function reveal(): string
    {
        $value = getenv($this->envVar);
        $token = false === $value ? '' : trim($value);

        if ('' === $token) {
            throw DiscoveryException::missingToken($this->envVar);
        }

        return $token;
    }
}
