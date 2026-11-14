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

namespace CommonDocTest\Aggregate\Discovery;

use CommonDoc\Aggregate\Discovery\DocTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocTarget::class)]
#[CoversMethod(DocTarget::class, 'from')]
final class DocTargetTest extends TestCase
{
    /**
     * The repository properties endpoint serializes a `true_false` property as a string, so the
     * boolean spelling of truth is not the one discovery actually reads.
     */
    #[Test]
    public function onboardsWithTheConventionalDirectoryWhenATrueFalseValueArrivesAsAString(): void
    {
        static::assertSame(
            'docs',
            DocTarget::from([['property_name' => 'doc-target', 'value' => 'true']]),
        );
    }

    #[Test]
    public function onboardsWithTheConventionalDirectoryWhenThePropertyIsTrue(): void
    {
        static::assertSame(
            'docs',
            DocTarget::from([['property_name' => 'doc-target', 'value' => true]]),
        );
    }

    #[Test]
    public function readsTheFirstMatchingPropertyOnly(): void
    {
        static::assertSame(
            'first',
            DocTarget::from([
                ['property_name' => 'doc-target', 'value' => 'first'],
                ['property_name' => 'doc-target', 'value' => 'second'],
            ]),
        );
    }

    #[Test]
    public function readsTheValueOfTheDocumentTargetProperty(): void
    {
        static::assertSame(
            'docs',
            DocTarget::from([
                ['property_name' => 'unrelated', 'value' => 'ignored'],
                ['property_name' => 'doc-target', 'value' => 'docs'],
            ]),
        );
    }

    #[Test]
    public function reportsNoTargetForAnEmptyPropertyList(): void
    {
        static::assertNull(DocTarget::from([]));
    }

    /**
     * A repository that carries the property at its default is off the site, which is only true if
     * the serialized spelling of false is read as the boolean it stands for.
     */
    #[Test]
    public function reportsNoTargetWhenAFalseValueArrivesAsAString(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target', 'value' => 'false']]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyCarriesNoNameKey(): void
    {
        static::assertNull(DocTarget::from([['value' => 'docs']]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyCarriesNoValueKey(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target']]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyHoldsAnEmptyValue(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target', 'value' => '']]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyHoldsANonStringNonBooleanValue(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target', 'value' => ['docs']]]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyHoldsNull(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target', 'value' => null]]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyHoldsOnlyWhitespace(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target', 'value' => '   ']]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyIsAbsent(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'unrelated', 'value' => 'docs']]));
    }

    #[Test]
    public function reportsNoTargetWhenThePropertyIsFalse(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target', 'value' => false]]));
    }

    #[Test]
    public function trimsTheValueItReads(): void
    {
        static::assertSame('docs', DocTarget::from([['property_name' => 'doc-target', 'value' => '  docs  ']]));
    }
}
