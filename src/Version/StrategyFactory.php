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

use CommonDoc\Aggregate\Discovery\Project;

/**
 * Builds the strategy a build asked for, bound to the project being resolved.
 *
 * The build injects this rather than choosing a class itself, so the choice of implementation is
 * made in one place and the build only ever holds a `StrategyInterface`.
 */
final readonly class StrategyFactory
{
    public function create(Strategy $strategy, Project $project): StrategyInterface
    {
        return match ($strategy) {
            Strategy::Ref  => new RefStrategy($project),
            Strategy::Tree => new TreeStrategy($project),
        };
    }
}
