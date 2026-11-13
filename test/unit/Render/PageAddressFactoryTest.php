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

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageAddressFactory::class)]
#[CoversMethod(PageAddressFactory::class, '__construct')]
#[CoversMethod(PageAddressFactory::class, 'forDocument')]
final class PageAddressFactoryTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function documentMappings(): array
    {
        return [
            'version root'            => [
                '1.0',
                'index.md',
                'aggregate-renderer/1.0/index.html',
                'https://docs.example.org/aggregate-renderer/1.0/',
            ],
            'directory index'         => [
                '1.0',
                'guide/index.md',
                'aggregate-renderer/1.0/guide/index.html',
                'https://docs.example.org/aggregate-renderer/1.0/guide/',
            ],
            'document in a directory' => [
                '1.0',
                'guide/setup.md',
                'aggregate-renderer/1.0/guide/setup/index.html',
                'https://docs.example.org/aggregate-renderer/1.0/guide/setup/',
            ],
            'deeply nested document'  => [
                '1.0',
                'guide/advanced/tuning.md',
                'aggregate-renderer/1.0/guide/advanced/tuning/index.html',
                'https://docs.example.org/aggregate-renderer/1.0/guide/advanced/tuning/',
            ],
            'patch version'           => [
                '1.0.3',
                'index.md',
                'aggregate-renderer/1.0.3/index.html',
                'https://docs.example.org/aggregate-renderer/1.0.3/',
            ],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unmappableDocumentPaths(): array
    {
        return [
            'empty'          => [''],
            'absolute'       => ['/docs/index.md'],
            'no extension'   => ['index'],
            'other format'   => ['index.rst'],
            'escapes upward' => ['guide/../../index.md'],
            'backslashes'    => ['guide\\setup.md'],
            'empty segment'  => ['guide//setup.md'],
        ];
    }

    #[Test]
    public function derivesOneFileAndOneAddressPerPage(): void
    {
        $factory = $this->factory();
        $target  = $this->target('1.0');

        $first  = $factory->forDocument($target, 'guide/setup.md');
        $second = $factory->forDocument($target, 'guide/setup.md');
        $other  = $factory->forDocument($target, 'guide/other.md');

        static::assertSame($first->file, $second->file);
        static::assertSame($first->address, $second->address);
        static::assertNotSame($first->file, $other->file);
        static::assertNotSame($first->address, $other->address);
    }

    #[Test]
    #[DataProvider('documentMappings')]
    public function mapsADocumentToItsFileAndItsAddress(
        string $version,
        string $documentPath,
        string $expectedFile,
        string $expectedAddress,
    ): void {
        $address = $this->factory()->forDocument($this->target($version), $documentPath);

        static::assertSame($expectedFile, $address->file);
        static::assertSame($expectedAddress, $address->address);
    }

    #[Test]
    public function mapsAPatchVersionToItsOwnDirectory(): void
    {
        $address = $this->factory()->forDocument($this->target('2.1.4'), 'guide/index.md');

        static::assertSame('aggregate-renderer/2.1.4/guide/index.html', $address->file);
        static::assertSame('https://docs.example.org/aggregate-renderer/2.1.4/guide/', $address->address);
    }

    #[Test]
    public function mapsTheVersionRootToTheVersionDirectory(): void
    {
        $address = $this->factory()->forDocument($this->target('2.1'), 'index.md');

        static::assertSame('aggregate-renderer/2.1/index.html', $address->file);
        static::assertSame('https://docs.example.org/aggregate-renderer/2.1/', $address->address);
    }

    #[Test]
    #[DataProvider('unmappableDocumentPaths')]
    public function rejectsADocumentPathThatCannotNameAPage(string $documentPath): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory()->forDocument($this->target('1.0'), $documentPath);
    }

    #[Test]
    public function rejectsAHostNameThatCannotAddressASite(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageAddressFactory('https://docs.example.org');
    }

    #[Test]
    public function rejectsAnEmptyHostName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageAddressFactory('');
    }

    private function factory(): PageAddressFactory
    {
        return new PageAddressFactory('docs.example.org');
    }

    private function target(string $version): Target
    {
        return new Target(
            project: new Project('aggregate-renderer', 'common-doc', 'aggregate-renderer', 'docs'),
            version: $version,
        );
    }
}
