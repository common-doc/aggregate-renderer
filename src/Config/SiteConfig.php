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

namespace CommonDoc\Aggregate\Config;

use CommonDoc\Aggregate\Version\Strategy;

/**
 * The decoded site configuration, with the default supplied by the reader.
 *
 * The token is deliberately absent: this value object carries the name of the environment variable
 * that holds it, never the token, so it is safe to hash, log and pass around.
 */
final readonly class SiteConfig
{
    /** @var non-empty-string */
    public string $organization;

    /** @var non-empty-string */
    public string $tokenEnvVar;

    /** @var non-empty-string */
    public string $outputDirectory;

    /** @var non-empty-string */
    public string $reportPath;

    /** @var non-empty-string */
    public string $cacheDirectory;

    /** @var non-empty-string */
    public string $hostname;

    public Strategy $strategy;

    /** @var array<string, non-empty-string> */
    public array $templateMap;

    /**
     * @param non-empty-string $organization
     * @param non-empty-string $tokenEnvVar
     * @param non-empty-string $outputDirectory
     * @param non-empty-string $reportPath
     * @param non-empty-string $cacheDirectory
     * @param non-empty-string $hostname
     * @param array<string, non-empty-string> $templateMap
     */
    public function __construct(
        string $organization,
        string $tokenEnvVar,
        string $outputDirectory,
        string $reportPath,
        string $cacheDirectory,
        string $hostname,
        Strategy $strategy,
        array $templateMap,
    ) {
        $this->organization    = $organization;
        $this->tokenEnvVar     = $tokenEnvVar;
        $this->outputDirectory = $outputDirectory;
        $this->reportPath      = $reportPath;
        $this->cacheDirectory  = $cacheDirectory;
        $this->hostname        = $hostname;
        $this->strategy        = $strategy;
        $this->templateMap     = $templateMap;
    }
}
