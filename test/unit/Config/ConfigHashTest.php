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

use CommonDoc\Aggregate\Config\ConfigHash;
use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Version\Strategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigHash::class)]
#[CoversMethod(ConfigHash::class, '__construct')]
final class ConfigHashTest extends TestCase
{
    #[Test]
    public function changesTheHashWhenAParticipatingFieldChanges(): void
    {
        $reference = new ConfigHash($this->config())->hash;

        static::assertNotSame($reference, new ConfigHash($this->config(hostname: 'docs.example.org'))->hash);
        static::assertNotSame($reference, new ConfigHash($this->config(organization: 'other-org'))->hash);
        static::assertNotSame($reference, new ConfigHash($this->config(strategy: Strategy::Tree))->hash);
    }

    #[Test]
    public function hashesTheSameConfigurationIdentically(): void
    {
        static::assertSame(
            new ConfigHash($this->config())->hash,
            new ConfigHash($this->config())->hash,
        );
    }

    #[Test]
    public function ignoresTheCacheDirectory(): void
    {
        static::assertSame(
            new ConfigHash($this->config())->hash,
            new ConfigHash($this->config(cacheDirectory: '/var/tmp/elsewhere'))->hash,
        );
    }

    #[Test]
    public function ignoresTheDeclarationOrderOfTheTemplateMap(): void
    {
        static::assertSame(
            new ConfigHash($this->config(templateMap: [
                'page'   => 'templates/page.php',
                'layout' => 'templates/layout.php',
            ]))->hash,
            new ConfigHash($this->config(templateMap: [
                'layout' => 'templates/layout.php',
                'page'   => 'templates/page.php',
            ]))->hash,
        );
    }

    /**
     * @param array<string, non-empty-string> $templateMap
     */
    private function config(
        string $organization = 'common-doc',
        string $hostname = 'docs.example.com',
        string $cacheDirectory = '.cache/multi-repo',
        Strategy $strategy = Strategy::Ref,
        array $templateMap = [],
    ): SiteConfig {
        return new SiteConfig(
            $organization,
            'COMMONDOC_TOKEN',
            'build/index.html',
            'build/report.json',
            $cacheDirectory,
            $hostname,
            $strategy,
            $templateMap,
        );
    }
}
