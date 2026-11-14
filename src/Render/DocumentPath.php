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

namespace CommonDoc\Aggregate\Render;

use CommonDoc\Aggregate\Output\RelativePath;
use InvalidArgumentException;

use function array_pop;
use function dirname;
use function explode;
use function implode;

/**
 * The document path a relative markdown href names, collapsed against the page's own directory.
 *
 * The href is written from a document's point of view, so `../usage-examples.md` means a different
 * document depending on which page carries it. Collapsing happens here so the result is a path
 * relative to the version root, which is what a route name is derived from.
 *
 * The result is handed to `RelativePath`, the class the build already uses to refuse a path that
 * leaves the tree. An href that escapes the version root is therefore refused for the same reason a
 * page address would be, and there is no second escape check to keep in step with that one.
 */
final readonly class DocumentPath
{
    /**
     * @param non-empty-string $value The document path, once collapsed and validated
     */
    private function __construct(
        public string $value,
    ) {}

    /**
     * @param non-empty-string $fromPath The document path of the page that carries the link
     *
     * @throws InvalidArgumentException When the collapsed path leaves the version root or is not a path
     */
    public static function resolve(string $fromPath, string $href): self
    {
        $directory = dirname($fromPath);
        $segments  = [];

        foreach (explode('/', '.' === $directory ? $href : "{$directory}/{$href}") as $segment) {
            if ('.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] === $segments) {
                    throw new InvalidArgumentException('The link escapes the version root.');
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return new self(new RelativePath(implode('/', $segments))->value());
    }
}
