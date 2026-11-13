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

use function hash;
use function json_encode;
use function ksort;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const SORT_STRING;

/**
 * Hash of a canonical encoding of the configuration.
 *
 * Every field participates except `cacheDirectory`: two runs that agree on what is rendered and
 * where it is published produce the same hash, whether or not they agree on where scratch work is
 * kept. Key order is fixed here rather than inherited from the reader, so a change to the reader
 * cannot silently change every hash.
 */
final readonly class ConfigHash
{
    /** @var non-empty-string */
    public string $hash;

    public function __construct(SiteConfig $config)
    {
        /** @var non-empty-string $hash */
        $hash = hash('sha256', json_encode($this->canonical($config), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $this->hash = $hash;
    }

    /**
     * @return array<string, array<string, string>|string>
     */
    private function canonical(SiteConfig $config): array
    {
        $templateMap = $config->templateMap;
        ksort($templateMap, SORT_STRING);

        $canonical = [
            'hostname'        => $config->hostname,
            'organization'    => $config->organization,
            'outputDirectory' => $config->outputDirectory,
            'reportPath'      => $config->reportPath,
            'strategy'        => $config->strategy->value,
            'templateMap'     => $templateMap,
            'tokenEnvVar'     => $config->tokenEnvVar,
        ];

        ksort($canonical, SORT_STRING);

        return $canonical;
    }
}
