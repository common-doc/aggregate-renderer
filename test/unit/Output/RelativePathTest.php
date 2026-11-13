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

namespace CommonDocTest\Aggregate\Output;

use CommonDoc\Aggregate\Output\RelativePath;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelativePath::class)]
#[CoversMethod(RelativePath::class, '__construct')]
#[CoversMethod(RelativePath::class, 'value')]
final class RelativePathTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function acceptedPaths(): array
    {
        return [
            'root file'   => ['CNAME'],
            'one segment' => ['404.html'],
            'nested'      => ['aggregate-renderer/1.0/guide/index.html'],
            'dotted'      => ['.well-known/security.txt'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedPaths(): array
    {
        return [
            'empty'          => [''],
            'absolute'       => ['/aggregate-renderer/1.0/index.html'],
            'drive'          => ['C:/aggregate-renderer/index.html'],
            'parent segment' => ['../index.html'],
            'inner parent'   => ['aggregate-renderer/../index.html'],
            'empty segment'  => ['aggregate-renderer//index.html'],
            'backslash'      => ['aggregate-renderer\\1.0\\index.html'],
            'space'          => ['aggregate-renderer/1.0/index copy.html'],
            'trailing slash' => ['aggregate-renderer/1.0/'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedPaths')]
    public function acceptsAPathInsideTheTree(string $path): void
    {
        static::assertSame($path, new RelativePath(value: $path)->value());
    }

    #[Test]
    #[DataProvider('rejectedPaths')]
    public function rejectsAPathThatCannotBeWrittenOrEscapeTheTree(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RelativePath(value: $path);
    }
}
