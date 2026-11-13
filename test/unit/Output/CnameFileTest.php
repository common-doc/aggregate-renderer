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

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Exception\MissingCnameException;
use CommonDoc\Aggregate\Output\CnameFile;
use CommonDoc\Aggregate\Output\OutputTree;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(CnameFile::class)]
#[CoversClass(MissingCnameException::class)]
#[CoversMethod(CnameFile::class, '__construct')]
#[CoversMethod(CnameFile::class, 'contents')]
#[CoversMethod(CnameFile::class, 'hostname')]
#[CoversMethod(CnameFile::class, 'verify')]
#[CoversMethod(CnameFile::class, 'write')]
final class CnameFileTest extends TestCase
{
    private string $directory;

    #[Test]
    public function acceptsAHostnameWithATrailingSlash(): void
    {
        static::assertSame('docs.example.org', $this->cname(hostname: 'docs.example.org/')->hostname());
    }

    #[Test]
    public function holdsTheBareHostnameAndOneNewline(): void
    {
        $cname = $this->cname(hostname: 'docs.example.org');

        static::assertSame("docs.example.org\n", $cname->contents());
        static::assertSame('docs.example.org', $cname->hostname());
    }

    #[Test]
    public function rejectsAHostnameThatCarriesAPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('carries a path');

        $this->cname(hostname: 'docs.example.org/guide');
    }

    #[Test]
    public function rejectsAnEmptyHostname(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The site hostname is empty');

        $this->cname(hostname: '');
    }

    #[Test]
    public function reportsAFileThatHoldsAnotherHostname(): void
    {
        $tree = $this->tree();
        $tree->reset();
        file_put_contents($tree->pathOf(relativePath: CnameFile::NAME), data: "other.example.org\n");

        try {
            $this->cname(hostname: 'docs.example.org')->verify();
        } catch (MissingCnameException $missing) {
            static::assertSame(ReasonCode::MissingCname, $missing->reasonCode);
            static::assertStringContainsString('other.example.org', $missing->getMessage());

            return;
        }

        static::fail('The mismatched site address file was not reported.');
    }

    #[Test]
    public function reportsAnAbsentFileAsMissing(): void
    {
        $this->tree()->reset();

        try {
            $this->cname(hostname: 'docs.example.org')->verify();
        } catch (MissingCnameException $missing) {
            static::assertSame(ReasonCode::MissingCname, $missing->reasonCode);
            static::assertStringContainsString('is absent', $missing->getMessage());

            return;
        }

        static::fail('The absent site address file was not reported.');
    }

    #[Test]
    public function verifiesTheFileItWrote(): void
    {
        $this->tree()->reset();

        $cname = $this->cname(hostname: 'docs.example.org');
        $cname->write();
        $cname->verify();

        static::assertSame("docs.example.org\n", $this->tree()->read(relativePath: CnameFile::NAME));
    }

    #[Test]
    public function writesTheFileAtTheRootOfTheTree(): void
    {
        $tree = $this->tree();
        $tree->reset();

        $this->cname(hostname: 'docs.example.org')->write();

        static::assertSame("docs.example.org\n", $tree->read(relativePath: CnameFile::NAME));
    }

    protected function setUp(): void
    {
        $this->directory =
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aggregate-renderer-cname-' . bin2hex(random_bytes(8));

        static::assertTrue(mkdir(
            directory  : $this->directory,
            permissions: 0o777,
            recursive  : true,
        ));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }
    }

    private function cname(string $hostname): CnameFile
    {
        return new CnameFile(
            tree    : $this->tree(),
            hostname: $hostname,
        );
    }

    private function removeDirectory(string $directory): void
    {
        $entries = glob($directory . DIRECTORY_SEPARATOR . '*');

        foreach (false === $entries ? [] : $entries as $entry) {
            if (is_dir($entry)) {
                $this->removeDirectory($entry);

                continue;
            }

            unlink($entry);
        }

        rmdir($directory);
    }

    private function tree(): OutputTree
    {
        return new OutputTree(root: $this->directory);
    }
}
