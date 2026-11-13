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

use function in_array;
use function is_dir;
use function mkdir;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;

/**
 * The directory a build publishes to.
 *
 * The root is guarded rather than trusted: a value that names a filesystem root, a drive root or the
 * current directory is refused at construction, because {@see self::empty()} deletes whatever it is
 * given and the configuration that supplies the path is a file an operator can edit.
 */
final readonly class OutputDirectory
{
    /** @var non-empty-string */
    private string $root;

    private DirectoryRemoval $removal;

    /**
     * @throws InvalidArgumentException When the root cannot be an output directory
     */
    public function __construct(string $root, ?DirectoryRemoval $removal = null)
    {
        $this->root    = self::requireRoot(root: $root);
        $this->removal = $removal ?? new DirectoryRemoval();
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the root cannot be an output directory
     */
    private static function requireRoot(string $root): string
    {
        $normalised = rtrim(
            string    : str_replace(
                search : '\\',
                replace: '/',
                subject: $root,
            ),
            characters: '/',
        );

        if (in_array(
            needle  : $normalised,
            haystack: ['', '.', '..', '/'],
            strict  : true,
        )) {
            throw new InvalidArgumentException(
                sprintf('The output directory "%s" is not a directory to empty.', $root),
            );
        }

        if (preg_match('~^[A-Za-z]:/?$~', $normalised) === 1) {
            throw new InvalidArgumentException(sprintf(
                'The output directory "%s" is a drive root, so it is not emptied.',
                $root,
            ));
        }

        return $normalised;
    }

    /**
     * Empties the directory and recreates it, so nothing from an earlier run survives.
     *
     * @throws RuntimeException When the directory cannot be removed or recreated
     */
    public function empty(): void
    {
        $this->removal->remove(path: $this->root);

        if (
            ! mkdir(
                directory  : $this->root,
                permissions: 0o777,
                recursive  : true,
            )
            && ! is_dir($this->root)
        ) {
            throw new RuntimeException(
                sprintf('The output directory "%s" cannot be created.', $this->root),
            );
        }
    }

    /**
     * @return non-empty-string
     */
    public function root(): string
    {
        return $this->root;
    }
}
