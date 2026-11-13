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

namespace CommonDocTest\Aggregate\Support;

use FilesystemIterator;
use RuntimeException;

use function bin2hex;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * A directory under the system temporary directory that a test can write into and remove.
 */
final class TemporaryDirectory
{
    public readonly string $path;

    public function __construct()
    {
        $this->path = sprintf('%s/%s', sys_get_temp_dir(), bin2hex(random_bytes(8)));

        if (
            ! mkdir(
                directory  : $this->path,
                permissions: 0o777,
                recursive  : true,
            )
            && ! is_dir($this->path)
        ) {
            throw new RuntimeException(sprintf('The temporary directory "%s" could not be created.', $this->path));
        }
    }

    public function remove(): void
    {
        if (! is_dir($this->path)) {
            return;
        }

        $this->removeDirectory($this->path);
    }

    private function removeDirectory(string $directory): void
    {
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            $path = $entry->getPathname();

            if ($entry->isDir()) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
