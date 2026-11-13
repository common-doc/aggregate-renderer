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

namespace CommonDoc\Aggregate\Version;

use InvalidArgumentException;

/**
 * The version decision seam.
 *
 * An implementation reads its candidates, decides, and returns. It performs no input, no output, no
 * process start and no version control operation, which is what makes it unit-testable with no
 * fixture and no filesystem, and what lets the two implementations be compared directly.
 *
 * @internal
 */
interface StrategyInterface
{
    /**
     * @param list<non-empty-string> $candidates Unordered, possibly duplicated candidate names
     *
     * @return list<Target> Ordered by version, de-duplicated, never keyed
     *
     * @throws InvalidArgumentException When an accepted candidate does not produce a valid target
     */
    public function resolve(array $candidates): array;
}
