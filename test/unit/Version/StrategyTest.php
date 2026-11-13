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

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Config\InvalidSiteConfig;
use CommonDoc\Aggregate\Version\Strategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Strategy::class)]
#[CoversMethod(Strategy::class, 'default')]
#[CoversMethod(Strategy::class, 'fromConfig')]
final class StrategyTest extends TestCase
{
    #[Test]
    public function defaultsToTheRefCorpus(): void
    {
        static::assertSame(Strategy::Ref, Strategy::default());
    }

    #[Test]
    public function readsTheRefCorpusFromConfiguration(): void
    {
        static::assertSame(Strategy::Ref, Strategy::fromConfig('ref'));
    }

    #[Test]
    public function readsTheTreeCorpusFromConfiguration(): void
    {
        static::assertSame(Strategy::Tree, Strategy::fromConfig('tree'));
    }

    #[Test]
    public function recordsTheReasonCodeForARejectedValue(): void
    {
        try {
            Strategy::fromConfig('Tree');

            static::fail('A value outside the two members must not be accepted.');
        } catch (InvalidSiteConfig $exception) {
            static::assertSame('versions.strategy', $exception->key);
            static::assertSame(ReasonCode::InvalidSiteConfig, $exception->reasonCode);
        }
    }

    #[Test]
    public function rejectsAValueThatNamesNoMember(): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage(
            'Invalid site configuration at "versions.strategy": "git" is not one of "ref" or "tree".',
        );

        Strategy::fromConfig('git');
    }
}
