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

namespace CommonDoc\Aggregate\Build;

/**
 * How one project fared in a build.
 *
 * `Partial` is the case that keeps the build honest: some pages rendered and some did not, so the
 * project is neither a success nor a failure and the report says exactly that.
 */
enum ProjectStatus: string
{
    case Ok      = 'ok';
    case Partial = 'partial';
    case Failed  = 'failed';
    case Skipped = 'skipped';
}
