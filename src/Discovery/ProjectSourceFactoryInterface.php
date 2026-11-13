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

use CommonDoc\Aggregate\Config\SiteConfig;

/**
 * The project source as the build obtains it.
 *
 * Which organization is listed and which environment variable holds the token are configuration
 * values, so they only exist once `site.json` has been read. The build therefore holds this factory
 * and asks for a source while building, rather than being handed one before the configuration is
 * known. One build asks once: the source reads the listing per call, and nothing about it outlives
 * the run.
 *
 * @internal
 */
interface ProjectSourceFactoryInterface
{
    public function create(SiteConfig $config): ProjectSourceInterface;
}
