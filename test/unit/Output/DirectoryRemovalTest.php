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

use CommonDoc\Aggregate\Output\DirectoryRemoval;
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

#[CoversClass(DirectoryRemoval::class)]
#[CoversMethod(DirectoryRemoval::class, 'remove')]
final class DirectoryRemovalTest extends TestCase
{
    private string $directory;

    #[Test]
    public function leavesAnAbsentPathAlone(): void
    {
        new DirectoryRemoval()->remove(path: "{$this->directory}/absent");

        static::assertDirectoryExists($this->directory);
    }

    #[Test]
    public function removesADirectoryAndEverythingUnderIt(): void
    {
        $nested = "{$this->directory}/aggregate-renderer/1.0/guide";
        static::assertTrue(mkdir(
            directory  : $nested,
            permissions: 0o777,
            recursive  : true,
        ));
        file_put_contents("{$nested}/index.html", data: '<html></html>');
        file_put_contents("{$this->directory}/CNAME", data: "docs.example.org\n");

        new DirectoryRemoval()->remove(path: $this->directory);

        static::assertDirectoryDoesNotExist($this->directory);
    }

    #[Test]
    public function removesASingleFile(): void
    {
        $file = "{$this->directory}/index.html";
        file_put_contents($file, data: '<html></html>');

        new DirectoryRemoval()->remove(path: $file);

        static::assertFileDoesNotExist($file);
        static::assertDirectoryExists($this->directory);
    }

    protected function setUp(): void
    {
        $this->directory =
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aggregate-renderer-removal-' . bin2hex(random_bytes(8));

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
}
