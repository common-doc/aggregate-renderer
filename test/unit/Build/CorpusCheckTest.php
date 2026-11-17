<?php

/*
 * This file is part of the common-doc/aggregate-renderer package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace CommonDocTest\Aggregate\Build;

use CommonDoc\Aggregate\Build\CorpusCheck;
use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Discovery\Project;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorpusCheck::class)]
#[CoversMethod(CorpusCheck::class, 'violation')]
final class CorpusCheckTest extends TestCase
{
    private static function project(string $sitePath, string $owner): Project
    {
        return new Project(
            name        : $sitePath,
            owner       : $owner,
            sitePath    : $sitePath,
            docDirectory: 'docs',
        );
    }

    #[Test]
    public function acceptsACorpusWithNoProjects(): void
    {
        static::assertNull(new CorpusCheck()->violation([]));
    }

    #[Test]
    public function acceptsDistinctSitePaths(): void
    {
        static::assertNull(new CorpusCheck()->violation([
            self::project('alpha', owner: 'common-doc'),
            self::project('beta', owner: 'common-doc'),
        ]));
    }

    #[Test]
    public function reportsDuplicateSlugWhenTwoProjectsDeriveOneSitePath(): void
    {
        static::assertSame(
            ReasonCode::DuplicateSlug,
            new CorpusCheck()->violation([
                self::project('alpha', owner: 'common-doc'),
                self::project('alpha', owner: 'webinertia'),
            ]),
        );
    }
}
