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
#[CoversMethod(PageAddress::class, 'path')]
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
                'https://docs.example.org/aggregate-renderer/1.0/index.html',
            ],
            'nested page'  => [
                'aggregate-renderer/1.0/guide/setup.html',
                'https://docs.example.org/aggregate-renderer/1.0/guide/setup.html',
            ],
            'site index'   => [
                'index.html',
                'https://docs.example.org/index.html',
            ],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unservablePairs(): array
    {
        return [
            'empty file'            => ['', 'https://docs.example.org/index.html'],
            'absolute file'         => ['/1.0/index.html', 'https://docs.example.org/1.0/index.html'],
            'not an html file'      => [
                'aggregate-renderer/1.0/page.md',
                'https://docs.example.org/aggregate-renderer/1.0/page.md',
            ],
            'plain http'            => ['index.html', 'http://docs.example.org/index.html'],
            'address names another' => [
                'aggregate-renderer/1.0/index.html',
                'https://docs.example.org/aggregate-renderer/2.0/index.html',
            ],
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
    public function explainsWhenTheAddressDoesNotNameABareHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The page address "https://docs.example.org/site/index.html" is not on https.',
        );

        new PageAddress(
            file   : 'index.html',
            address: 'https://docs.example.org/site/index.html',
        );
    }

    #[Test]
    public function explainsWhenTheAddressDoesNotNameTheOutputFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The page address "http://docs.example.org/index.html" does not name the output file "index.html".',
        );

        new PageAddress(
            file   : 'index.html',
            address: 'http://docs.example.org/index.html',
        );
    }

    #[Test]
    public function explainsWhenTheAddressNamesAnotherFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The page address "https://docs.example.org/aggregate-renderer/2.0/index.html" '
                . 'does not name the output file "aggregate-renderer/1.0/index.html".',
        );

        new PageAddress(
            file   : 'aggregate-renderer/1.0/index.html',
            address: 'https://docs.example.org/aggregate-renderer/2.0/index.html',
        );
    }

    #[Test]
    public function explainsWhenTheAddressNamesOnlyTheTailOfTheFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The page address "https://docs.example.org/ocs/index.html" does not name the output file "docs/index.html".',
        );

        new PageAddress(
            file   : 'docs/index.html',
            address: 'https://docs.example.org/ocs/index.html',
        );
    }

    #[Test]
    public function explainsWhenTheFileIsNotAnHtmlFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The output file "aggregate-renderer/1.0/page.md" is not an html file.',
        );

        new PageAddress(
            file   : 'aggregate-renderer/1.0/page.md',
            address: 'https://docs.example.org/aggregate-renderer/1.0/page.md',
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
            address: 'https://docs.example.org/1.0/index.html',
        );
    }

    #[Test]
    #[DataProvider('servablePairs')]
    public function namesTheAddressAsAPathBelowTheSiteRoot(string $file, string $address): void
    {
        $page = new PageAddress(
            file   : $file,
            address: $address,
        );

        static::assertSame("/{$file}", $page->path());
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
