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
use Override;

/**
 * Builds the report writer from the configured report path.
 */
final readonly class ReportFileFactory implements ReportFileFactoryInterface
{
    #[Override]
    public function create(SiteConfig $config): ReportFile
    {
        return new ReportFile(path: $config->reportPath);
    }
}
