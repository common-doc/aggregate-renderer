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

namespace CommonDocTest\Aggregate\Version;

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Target::class)]
#[CoversMethod(Target::class, '__construct')]
#[CoversMethod(Target::class, 'compare')]
final class TargetTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function acceptedVersions(): array
    {
        return [
            'minor' => ['1.0'],
            'major' => ['12.34'],
            'patch' => ['1.0.3'],
            'zeros' => ['0.0.0'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedVersions(): array
    {
        return [
            'empty'        => [''],
            'major only'   => ['1'],
            'four parts'   => ['1.0.0.0'],
            'prefix v'     => ['v1.0'],
            'range'        => ['1.0.x'],
            'two dots'     => ['1..0'],
            'word'         => ['latest'],
            'trailing dot' => ['1.0.'],
        ];
    }

    private static function project(string $sitePath = 'aggregate-renderer'): Project
    {
        return new Project(
            name        : 'aggregate-renderer',
            owner       : 'common-doc',
            sitePath    : $sitePath,
            docDirectory: 'docs',
        );
    }

    #[Test]
    #[DataProvider('acceptedVersions')]
    public function acceptsTheVersionGrammar(string $version): void
    {
        $target = new Target(
            project: self::project(),
            version: $version,
        );

        static::assertSame($version, $target->version);
    }

    #[Test]
    public function comparesTheSameProjectAtTheSameVersionAsEqual(): void
    {
        static::assertSame(
            0,
            Target::compare(
                new Target(
                    project: self::project(),
                    version: '2.1.4',
                ),
                new Target(
                    project: self::project(),
                    version: '2.1.4',
                ),
            ),
        );
    }

    #[Test]
    public function fallsBackToTheWrittenFormWhenSegmentsAreEqual(): void
    {
        static::assertGreaterThan(
            0,
            Target::compare(
                new Target(
                    project: self::project(),
                    version: '1.0',
                ),
                new Target(
                    project: self::project(),
                    version: '01.0',
                ),
            ),
        );
    }

    #[Test]
    public function namesTheProjectAndTheVersionWhenRejecting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Project "aggregate-renderer" declares the version "1.0.x", which is neither MAJOR.MINOR nor MAJOR.MINOR.PATCH.',
        );

        new Target(
            project: self::project(),
            version: '1.0.x',
        );
    }

    #[Test]
    public function ordersBySitePathFirst(): void
    {
        $first = new Target(
            project: self::project(sitePath: 'alpha'),
            version: '9.0',
        );
        $second = new Target(
            project: self::project(sitePath: 'beta'),
            version: '1.0',
        );

        static::assertLessThan(0, Target::compare($first, $second));
        static::assertGreaterThan(0, Target::compare($second, $first));
    }

    #[Test]
    public function ordersVersionsOfOneProjectNumericallyPerSegment(): void
    {
        $older = new Target(
            project: self::project(),
            version: '1.9.0',
        );
        $newer = new Target(
            project: self::project(),
            version: '1.10.0',
        );

        static::assertLessThan(0, Target::compare($older, $newer));
        static::assertGreaterThan(0, Target::compare($newer, $older));
    }

    #[Test]
    #[DataProvider('rejectedVersions')]
    public function rejectsAVersionOutsideTheGrammar(string $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Target(
            project: self::project(),
            version: $version,
        );
    }

    #[Test]
    public function treatsAMissingSegmentAsZero(): void
    {
        $short = new Target(
            project: self::project(),
            version: '1.0',
        );
        $long = new Target(
            project: self::project(),
            version: '1.0.0',
        );

        static::assertLessThan(0, Target::compare($short, $long));
    }
}
