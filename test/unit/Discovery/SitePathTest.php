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

use CommonDoc\Aggregate\Discovery\SitePath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SitePath::class)]
#[CoversMethod(SitePath::class, 'derive')]
final class SitePathTest extends TestCase
{
    #[Test]
    public function collapsesConsecutiveSeparatorsIntoOne(): void
    {
        static::assertSame('a-b', SitePath::derive('a___b')->value);
        static::assertSame('a-b', SitePath::derive('a .-_ b')->value);
    }

    #[Test]
    public function collapsesSpacesIntoOneSeparator(): void
    {
        static::assertSame('my-docs', SitePath::derive('my docs')->value);
    }

    #[Test]
    public function derivesOnePathForTwoNamesThatDifferOnlyInPunctuation(): void
    {
        static::assertSame('foo-bar', SitePath::derive('Foo_Bar')->value);
        static::assertSame('foo-bar', SitePath::derive('foo.bar')->value);
        static::assertSame(SitePath::derive('Foo_Bar')->value, SitePath::derive('foo-bar')->value);
    }

    #[Test]
    public function keepsDigitsOfAVersionedName(): void
    {
        static::assertSame('site-2-tools', SitePath::derive('Site 2 Tools')->value);
    }

    #[Test]
    public function lowercasesANameThatCarriesUppercase(): void
    {
        static::assertSame('commondoc', SitePath::derive('CommonDoc')->value);
    }

    #[Test]
    public function refusesANameThatDerivesNothing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The repository name "___" does not derive a site path.');

        SitePath::derive('___');
    }

    #[Test]
    public function refusesAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SitePath::derive('');
    }

    #[Test]
    public function trimsLeadingAndTrailingSeparators(): void
    {
        static::assertSame('docs', SitePath::derive('--docs--')->value);
        static::assertSame('docs', SitePath::derive('_docs_')->value);
    }
}
