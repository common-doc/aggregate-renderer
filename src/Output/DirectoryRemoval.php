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

use RuntimeException;

use function is_dir;
use function is_file;
use function is_link;
use function rmdir;
use function scandir;
use function sprintf;
use function unlink;

/**
 * Removes a directory and everything under it, or fails.
 *
 * This is the one place a build deletes anything, so it is isolated and deliberately loud: a file it
 * cannot remove is an exception rather than a warning, because a tree left half emptied would publish
 * a mixture of this run and an earlier one.
 */
final readonly class DirectoryRemoval
{
    /**
     * @throws RuntimeException When something under the path cannot be removed
     */
    public function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (! unlink($path)) {
                throw new RuntimeException(sprintf('The file "%s" cannot be removed.', $path));
            }

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        foreach (false === $entries ? [] : $entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $this->remove(path: "{$path}/{$entry}");
        }

        if (! rmdir($path)) {
            throw new RuntimeException(sprintf('The directory "%s" cannot be removed.', $path));
        }
    }
}
