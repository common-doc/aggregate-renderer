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

namespace CommonDoc\Aggregate\Report;

use CommonDoc\Aggregate\Config\SiteConfig;
use InvalidArgumentException;

/**
 * The report writer as the command obtains it.
 *
 * The report path is the site's, so the writer can only be built once the configuration has been
 * read. The command therefore holds this factory instead of constructing the writer itself, which
 * keeps the report location a configuration concern rather than a command one.
 *
 * @internal
 */
interface ReportFileFactoryInterface
{
    /**
     * @throws InvalidArgumentException When the configured report path cannot name a file
     */
    public function create(SiteConfig $config): ReportFile;
}
