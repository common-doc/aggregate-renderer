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

namespace CommonDoc\Aggregate\Source;

use CommonDoc\Aggregate\Config\SiteConfig;
use InvalidArgumentException;

/**
 * The source as the build obtains it.
 *
 * A source is bound to a cache directory and a version strategy, both of which only exist once a site
 * configuration has been read, so the build holds this factory and asks for the source during the
 * build rather than being handed one earlier. One build asks once, so the checkout cache is shared by
 * every collaborator that reads through it.
 *
 * @internal
 */
interface SourceFactoryInterface
{
    /**
     * @throws InvalidArgumentException When the configuration cannot produce a checkout directory
     */
    public function create(SiteConfig $config): SourceInterface;
}
