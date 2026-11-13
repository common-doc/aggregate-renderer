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

use CommonDoc\Aggregate\Output\OutputDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function random_bytes;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(OutputDirectory::class)]
#[CoversMethod(OutputDirectory::class, '__construct')]
#[CoversMethod(OutputDirectory::class, 'empty')]
#[CoversMethod(OutputDirectory::class, 'root')]
final class OutputDirectoryTest extends TestCase
{
    private string $directory;

    /**
     * @return array<string, array{string}>
     */
    public static function rootsThatAreNeverEmptied(): array
    {
        return [
            'empty'      => [''],
            'current'    => ['.'],
            'parent'     => ['..'],
            'unix root'  => ['/'],
            'drive root' => ['C:/'],
            'bare drive' => ['C:'],
            'trailing'   => ['///'],
        ];
    }

    #[Test]
    public function acceptsAPathEndingInASegmentThatLooksLikeADrive(): void
    {
        static::assertSame(
            'docs/a:',
            new OutputDirectory(root: 'docs/a:')->root(),
        );
    }

    #[Test]
    public function emptiesTheDirectoryAndRecreatesIt(): void
    {
        $nested = "{$this->directory}/aggregate-renderer/1.0";
        static::assertTrue(mkdir(
            directory  : $nested,
            permissions: 0o777,
            recursive  : true,
        ));
        file_put_contents("{$nested}/index.html", data: '<html></html>');

        new OutputDirectory(root: $this->directory)->empty();

        static::assertDirectoryExists($this->directory);
        static::assertDirectoryDoesNotExist("{$this->directory}/aggregate-renderer");
    }

    #[Test]
    public function normalisesTheRoot(): void
    {
        $directory = new OutputDirectory(root: $this->directory . DIRECTORY_SEPARATOR);

        static::assertSame(
            str_replace(
                search : '\\',
                replace: '/',
                subject: $this->directory,
            ),
            $directory->root(),
        );
    }

    #[Test]
    public function normalisesTheSeparatorsOfAWindowsStyleRoot(): void
    {
        static::assertSame('C:/site/output', new OutputDirectory(root: 'C:\\site\\output')->root());
    }

    #[Test]
    public function recreatesADirectoryThatDoesNotExistYet(): void
    {
        $root = "{$this->directory}/absent";

        new OutputDirectory(root: $root)->empty();

        static::assertDirectoryExists($root);
        static::assertNotFalse(file_put_contents(
            filename: "{$root}/index.html",
            data    : '<html></html>',
        ));
    }

    #[Test]
    #[DataProvider('rootsThatAreNeverEmptied')]
    public function rejectsARootThatIsNeverEmptied(string $root): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutputDirectory(root: $root);
    }

    #[Test]
    public function reportsADirectoryThatCannotBeCreated(): void
    {
        static::assertNotFalse(file_put_contents(
            filename: "{$this->directory}/blocked",
            data    : 'not a directory',
        ));

        set_error_handler(static fn(): bool => true);

        try {
            new OutputDirectory(root: "{$this->directory}/blocked/output")->empty();
        } catch (RuntimeException $failure) {
            static::assertSame(
                sprintf(
                    'The output directory "%s/blocked/output" cannot be created.',
                    str_replace(
                        search : '\\',
                        replace: '/',
                        subject: $this->directory,
                    ),
                ),
                $failure->getMessage(),
            );

            return;
        } finally {
            restore_error_handler();
        }

        static::fail('An output directory that cannot be created is reported.');
    }

    protected function setUp(): void
    {
        $this->directory =
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aggregate-renderer-output-' . bin2hex(random_bytes(8));

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
