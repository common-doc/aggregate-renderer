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
    public function reportsNoTargetWhenThePropertyHoldsNoString(): void
    {
        static::assertNull(DocTarget::from([['property_name' => 'doc-target', 'value' => ['docs']]]));
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
    public function trimsTheValueItReads(): void
    {
        static::assertSame('docs', DocTarget::from([['property_name' => 'doc-target', 'value' => '  docs  ']]));
    }
}
