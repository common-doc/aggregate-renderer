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

namespace CommonDocTest\Aggregate\Source;

use CommonDoc\Aggregate\Source\TreeOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function implode;

#[CoversClass(TreeOutput::class)]
#[CoversMethod(TreeOutput::class, 'directories')]
#[CoversMethod(TreeOutput::class, 'documents')]
#[CoversMethod(TreeOutput::class, 'paths')]
final class TreeOutputTest extends TestCase
{
    #[Test]
    public function ignoresAPathThatOnlySharesTheRootPrefix(): void
    {
        static::assertSame([], TreeOutput::paths('documentation/index.md', 'docs'));
    }

    #[Test]
    public function namesPathsRelativeToTheSourceRoot(): void
    {
        $listing = implode("\n", [
            'docs/guide/index.md',
            'docs/install.md',
            'docs/notes.txt',
            'other/index.md',
            '',
        ]);

        static::assertSame(
            ['guide/index.md', 'install.md', 'notes.txt'],
            TreeOutput::paths($listing, 'docs'),
        );
    }

    #[Test]
    public function reportsOnlyTheMarkdownDocuments(): void
    {
        static::assertSame(
            ['guide/index.md', 'install.md'],
            TreeOutput::documents(['guide/index.md', 'diagram.svg', 'install.md']),
        );
    }

    #[Test]
    public function reportsTheImmediateSubdirectoriesOfTheSourceRoot(): void
    {
        static::assertSame(
            ['1.0', '1.1'],
            TreeOutput::directories(['1.0', '1.0/index.md', '1.1', '1.1/index.md']),
        );
    }
}
