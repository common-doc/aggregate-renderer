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

use CommonDoc\Aggregate\Config\NavigationConfig;
use CommonDoc\Aggregate\Config\NavigationReader;
use CommonDoc\Aggregate\Config\Section;
use CommonDoc\Aggregate\Exception\InvalidSiteConfigException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NavigationReader::class)]
#[CoversClass(NavigationConfig::class)]
#[CoversMethod(NavigationReader::class, 'read')]
#[CoversMethod(NavigationConfig::class, '__construct')]
final class NavigationReaderTest extends TestCase
{
    /**
     * @param array<string, mixed> $navigation
     */
    private static function site(array $navigation): Section
    {
        return new Section(['navigation' => $navigation], 'site');
    }

    #[Test]
    public function defaultsToNavigationOffWhenConstructedDirectly(): void
    {
        $config = new NavigationConfig();

        static::assertFalse($config->enabled);
        static::assertSame(NavigationConfig::DEFAULT_IDENTIFIER, $config->identifier);
    }

    #[Test]
    public function enablesNavigationAndKeepsTheDefaultIdentifier(): void
    {
        $config = NavigationReader::read(self::site(['enabled' => true]));

        static::assertTrue($config->enabled);
        static::assertSame(NavigationConfig::DEFAULT_IDENTIFIER, $config->identifier);
    }

    #[Test]
    public function leavesNavigationOffWhenTheBlockIsAbsent(): void
    {
        $config = NavigationReader::read(self::site([]));

        static::assertFalse($config->enabled);
        static::assertSame(NavigationConfig::DEFAULT_IDENTIFIER, $config->identifier);
    }

    #[Test]
    public function readsTheIdentifierBesideTheFlag(): void
    {
        $config = NavigationReader::read(self::site(['enabled' => true, 'identifier' => 'manual']));

        static::assertTrue($config->enabled);
        static::assertSame('manual', $config->identifier);
    }

    #[Test]
    public function rejectsAKeyTheNavigationBlockDoesNotDefine(): void
    {
        $this->expectException(InvalidSiteConfigException::class);

        NavigationReader::read(self::site(['routesPath' => 'routes.json']));
    }

    #[Test]
    public function rejectsAnEmptyIdentifierOnTheValueObject(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NavigationConfig(identifier: '');
    }

    #[Test]
    public function rejectsAnIdentifierThatIsNotASingleName(): void
    {
        $this->expectException(InvalidSiteConfigException::class);
        $this->expectExceptionMessageMatches('/site\.navigation\.identifier/');

        NavigationReader::read(self::site(['identifier' => 'docs/api']));
    }
}
