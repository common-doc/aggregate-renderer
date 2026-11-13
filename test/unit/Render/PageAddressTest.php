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

namespace CommonDocTest\Aggregate\Render;

use CommonDoc\Aggregate\Render\PageAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageAddress::class)]
#[CoversMethod(PageAddress::class, '__construct')]
final class PageAddressTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function servablePairs(): array
    {
        return [
            'version root' => [
                'aggregate-renderer/1.0/index.html',
                'https://docs.example.org/aggregate-renderer/1.0/',
            ],
            'nested page'  => [
                'aggregate-renderer/1.0/guide/setup/index.html',
                'https://docs.example.org/aggregate-renderer/1.0/guide/setup/',
            ],
            'site index'   => [
                'index.html',
                'https://docs.example.org/',
            ],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unservablePairs(): array
    {
        return [
            'empty file'            => ['', 'https://docs.example.org/'],
            'absolute file'         => ['/1.0/index.html', 'https://docs.example.org/1.0/'],
            'not a page file'       => [
                'aggregate-renderer/1.0/page.html',
                'https://docs.example.org/aggregate-renderer/1.0/',
            ],
            'plain http'            => ['index.html', 'http://docs.example.org/'],
            'no trailing slash'     => ['index.html', 'https://docs.example.org'],
            'address names another' => ['aggregate-renderer/1.0/index.html', 'https://docs.example.org/2.0/'],
        ];
    }

    #[Test]
    #[DataProvider('servablePairs')]
    public function acceptsOneFileAndTheAddressItServes(string $file, string $address): void
    {
        $page = new PageAddress(
            file   : $file,
            address: $address,
        );

        static::assertSame($file, $page->file);
        static::assertSame($address, $page->address);
    }

    #[Test]
    public function explainsWhenTheAddressIsNotAnHttpsDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The page address "http://docs.example.org/" is not a directory address on https.',
        );

        new PageAddress(
            file   : 'index.html',
            address: 'http://docs.example.org/',
        );
    }

    #[Test]
    public function explainsWhenTheAddressNamesAnotherFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The page address "https://docs.example.org/2.0/" does not name the output file "aggregate-renderer/1.0/index.html".',
        );

        new PageAddress(
            file   : 'aggregate-renderer/1.0/index.html',
            address: 'https://docs.example.org/2.0/',
        );
    }

    #[Test]
    public function explainsWhenTheAddressNamesOnlyTheTailOfTheDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The page address "https://docs.example.org/ocs/" does not name the output file "docs/index.html".',
        );

        new PageAddress(
            file   : 'docs/index.html',
            address: 'https://docs.example.org/ocs/',
        );
    }

    #[Test]
    public function explainsWhenTheFileDoesNotServeADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The output file "aggregate-renderer/1.0/page.html" does not serve a directory address.',
        );

        new PageAddress(
            file   : 'aggregate-renderer/1.0/page.html',
            address: 'https://docs.example.org/aggregate-renderer/1.0/',
        );
    }

    #[Test]
    public function explainsWhichMemberIsNotRelative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The output file "/1.0/index.html" is not relative to the output directory.',
        );

        new PageAddress(
            file   : '/1.0/index.html',
            address: 'https://docs.example.org/1.0/',
        );
    }

    #[Test]
    #[DataProvider('unservablePairs')]
    public function rejectsAPairThatCannotServeOnePage(string $file, string $address): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageAddress(
            file   : $file,
            address: $address,
        );
    }
}
