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

namespace CommonDoc\Aggregate\Navigation;

use CommonDoc\Aggregate\Build\Corpus;
use Webware\Router\RouteProviderInterface;

/**
 * Builds the route provider for one corpus.
 *
 * A provider holds the corpus it registers, so it cannot be a shared service: one instance exists per
 * build and it is discarded with the build. This factory is the seam that makes that explicit, and
 * the place where a disabled navigation block resolves to no provider at all rather than to a
 * provider that registers nothing.
 *
 * @internal
 */
interface RouteProviderFactoryInterface
{
    /** Returns null when navigation is disabled. */
    public function create(Corpus $corpus): ?RouteProviderInterface;
}
