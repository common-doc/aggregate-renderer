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

namespace CommonDocTest\Aggregate\Config;

use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Version\Strategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteConfig::class)]
#[CoversMethod(SiteConfig::class, '__construct')]
final class SiteConfigTest extends TestCase
{
    private static function config(): SiteConfig
    {
        return new SiteConfig(
            organization   : 'common-doc',
            tokenEnvVar    : 'AGGREGATE_RENDER_TOKEN',
            outputDirectory: 'build/site',
            reportPath     : 'build/site-report.json',
            cacheDirectory : 'build/cache',
            hostname       : 'docs.example.org',
            strategy       : Strategy::Tree,
            templateMap    : ['document' => 'app/document'],
        );
    }

    #[Test]
    public function acceptsAnEmptyTemplateMap(): void
    {
        $config = new SiteConfig(
            organization   : 'common-doc',
            tokenEnvVar    : 'AGGREGATE_RENDER_TOKEN',
            outputDirectory: 'build/site',
            reportPath     : 'build/site-report.json',
            cacheDirectory : 'build/cache',
            hostname       : 'docs.example.org',
            strategy       : Strategy::Ref,
            templateMap    : [],
        );

        static::assertSame([], $config->templateMap);
    }

    #[Test]
    public function carriesEveryRequiredKeyOfTheSchema(): void
    {
        $config = self::config();

        static::assertSame('common-doc', $config->organization);
        static::assertSame('AGGREGATE_RENDER_TOKEN', $config->tokenEnvVar);
        static::assertSame('build/site', $config->outputDirectory);
        static::assertSame('build/site-report.json', $config->reportPath);
        static::assertSame('build/cache', $config->cacheDirectory);
        static::assertSame('docs.example.org', $config->hostname);
        static::assertSame(Strategy::Tree, $config->strategy);
        static::assertSame(['document' => 'app/document'], $config->templateMap);
    }

    #[Test]
    public function carriesTheNameOfTheTokenVariableRatherThanTheToken(): void
    {
        static::assertSame('AGGREGATE_RENDER_TOKEN', self::config()->tokenEnvVar);
    }
}
