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

namespace CommonDoc\Aggregate\Output;

use InvalidArgumentException;
use RuntimeException;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;
use function str_replace;

/**
 * The published tree, and the only way anything inside it is written or read back.
 *
 * Every path here is relative to the root, so a page address and the file that serves it can be
 * compared without knowing where the build ran. Writing normalises line endings to LF rather than
 * leaving that to each caller, which is part of what makes two builds of an unchanged corpus
 * byte-identical.
 *
 * The directory lifecycle is delegated: {@see OutputDirectory} guards the root and empties it, and
 * {@see RelativePath} refuses a path that is absolute, ambiguous or escaping. A caller can therefore
 * ask for a file by name without being able to name the checkout.
 */
final readonly class OutputTree
{
    private OutputDirectory $directory;

    /**
     * @throws InvalidArgumentException When the root cannot be an output directory
     */
    public function __construct(string $root, ?DirectoryRemoval $removal = null)
    {
        $this->directory = new OutputDirectory(
            root   : $root,
            removal: $removal,
        );
    }

    /**
     * Normalises every line ending to LF, which the contract requires of generated files.
     */
    private static function withLf(string $contents): string
    {
        return str_replace(
            search : ["\r\n", "\r"],
            replace: "\n",
            subject: $contents,
        );
    }

    /**
     * @throws InvalidArgumentException When the path is not relative to the output directory
     */
    public function exists(string $relativePath): bool
    {
        return file_exists(filename: $this->pathOf(relativePath: $relativePath));
    }

    /**
     * Path of one file in the tree, for reading it back.
     *
     * @throws InvalidArgumentException When the path is not relative to the output directory
     */
    public function pathOf(string $relativePath): string
    {
        return $this->directory->root() . '/' . new RelativePath(value: $relativePath)->value();
    }

    /**
     * @throws InvalidArgumentException When the path is not relative to the output directory
     * @throws RuntimeException         When the file cannot be read
     */
    public function read(string $relativePath): string
    {
        $path = $this->pathOf(relativePath: $relativePath);

        if (! is_file(filename: $path)) {
            throw new RuntimeException(sprintf('The file "%s" cannot be read.', $relativePath));
        }

        $contents = file_get_contents(filename: $path);

        if (false === $contents) {
            throw new RuntimeException(sprintf('The file "%s" cannot be read.', $relativePath));
        }

        return $contents;
    }

    /**
     * Empties the tree and recreates the root, so nothing from an earlier run survives.
     *
     * @throws RuntimeException When the tree cannot be removed or recreated
     */
    public function reset(): void
    {
        $this->directory->empty();
    }

    /**
     * @return non-empty-string
     */
    public function root(): string
    {
        return $this->directory->root();
    }

    /**
     * @throws InvalidArgumentException When the path is not relative to the output directory
     * @throws RuntimeException         When the file cannot be written
     */
    public function write(string $relativePath, string $contents): void
    {
        $path      = $this->pathOf(relativePath: $relativePath);
        $directory = dirname(path: $path);

        if (
            ! is_dir($directory)
            && ! mkdir(
                directory  : $directory,
                permissions: 0o777,
                recursive  : true,
            )
        ) {
            throw new RuntimeException(
                sprintf('The directory "%s" cannot be created.', $directory),
            );
        }

        if (
            false === file_put_contents(
                filename: $path,
                data    : self::withLf(contents: $contents),
            )
        ) {
            throw new RuntimeException(
                sprintf('The file "%s" cannot be written.', $relativePath),
            );
        }
    }
}
