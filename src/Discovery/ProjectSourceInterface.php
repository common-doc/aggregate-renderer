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

/**
 * The seam every discovery implementation sits behind.
 *
 * Nothing downstream knows how a corpus was found, which keeps the GitHub boundary out of the build
 * and makes the build testable against a fixed corpus.
 *
 * @internal
 */
interface ProjectSourceInterface
{
    /** @return list<Project> */
    public function all(): array;
}
