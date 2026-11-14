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

use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Source\SourceInterface;

/**
 * Builds the corpus registration a site configuration describes.
 *
 * Registration is scoped to one build twice over: it holds the source for this configuration, and the
 * route provider it builds holds that configuration's host name and navigation identifier. It cannot
 * be a shared service without one build's corpus leaking into the next, so it is built here.
 *
 * @internal
 */
interface CorpusRegistrationFactoryInterface
{
    public function create(SiteConfig $config, SourceInterface $source): CorpusRegistrationInterface;
}
