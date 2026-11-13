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

use CommonDoc\Aggregate\Output\OutputTree;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function glob;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(OutputTree::class)]
#[CoversMethod(OutputTree::class, '__construct')]
#[CoversMethod(OutputTree::class, 'exists')]
#[CoversMethod(OutputTree::class, 'pathOf')]
#[CoversMethod(OutputTree::class, 'read')]
#[CoversMethod(OutputTree::class, 'reset')]
#[CoversMethod(OutputTree::class, 'root')]
#[CoversMethod(OutputTree::class, 'write')]
final class OutputTreeTest extends TestCase
{
    private string $directory;

    #[Test]
    public function namesAFileRelativeToTheRoot(): void
    {
        $tree = $this->tree();

        static::assertSame(
            sprintf('%s/aggregate-renderer/1.0/index.html', $tree->root()),
            $tree->pathOf(relativePath: 'aggregate-renderer/1.0/index.html'),
        );
    }

    #[Test]
    public function refusesToReadAPageThatWasNotWritten(): void
    {
        $tree = $this->tree();
        $tree->reset();

        static::assertFalse($tree->exists(relativePath: 'aggregate-renderer/1.0/index.html'));

        $this->expectException(RuntimeException::class);

        $tree->read(relativePath: 'aggregate-renderer/1.0/index.html');
    }

    #[Test]
    public function rejectsAPathThatEscapesTheTree(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->tree()->write(
            relativePath: '../index.html',
            contents    : '',
        );
    }

    #[Test]
    public function rejectsARootThatIsNeverEmptied(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutputTree(root: '.');
    }

    #[Test]
    public function resetsTheTreeSoNothingFromAnEarlierRunSurvives(): void
    {
        $tree = $this->tree();
        $tree->reset();
        $tree->write(
            relativePath: 'CNAME',
            contents    : "docs.example.org\n",
        );

        $tree->reset();

        static::assertFalse($tree->exists(relativePath: 'CNAME'));
        static::assertDirectoryExists($tree->root());
    }

    #[Test]
    public function writesAndReadsAPage(): void
    {
        $tree = $this->tree();
        $tree->reset();

        $tree->write(
            relativePath: 'aggregate-renderer/1.0/index.html',
            contents    : '<html></html>',
        );

        static::assertTrue($tree->exists(relativePath: 'aggregate-renderer/1.0/index.html'));
        static::assertSame('<html></html>', $tree->read(relativePath: 'aggregate-renderer/1.0/index.html'));
    }

    #[Test]
    public function writesLineEndingsAsLf(): void
    {
        $tree = $this->tree();
        $tree->reset();

        $tree->write(
            relativePath: 'CNAME',
            contents    : "docs.example.org\r\n",
        );

        static::assertSame("docs.example.org\n", $tree->read(relativePath: 'CNAME'));
    }

    protected function setUp(): void
    {
        $this->directory =
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aggregate-renderer-tree-' . bin2hex(random_bytes(8));

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

    private function tree(): OutputTree
    {
        return new OutputTree(root: $this->directory);
    }
}
