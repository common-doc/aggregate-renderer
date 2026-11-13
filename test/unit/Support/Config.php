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

namespace CommonDocTest\Aggregate\Support;

use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Version\Strategy;

/**
 * A site configuration the tests can vary one field of without restating the rest.
 */
final class Config
{
    /**
     * @param array<string, non-empty-string> $templateMap
     */
    public static function site(
        string $outputDirectory = 'build',
        string $reportPath = 'build/report.json',
        string $cacheDirectory = '.cache',
        array $templateMap = [],
        Strategy $strategy = Strategy::Ref,
    ): SiteConfig {
        return new SiteConfig(
            organization   : 'common-doc',
            tokenEnvVar    : 'COMMONDOC_TOKEN',
            outputDirectory: $outputDirectory,
            reportPath     : $reportPath,
            cacheDirectory : $cacheDirectory,
            hostname       : 'docs.example.org',
            strategy       : $strategy,
            templateMap    : $templateMap,
        );
    }
}
