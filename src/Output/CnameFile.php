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

use function rtrim;
use function sprintf;
use function str_contains;

/**
 * The `CNAME` file at the root of the published tree.
 *
 * The content is the bare hostname plus one trailing newline and nothing else, which is what the host
 * reads. Writing and verifying live together because a file that is written by one rule and checked by
 * another drifts: the check compares the bytes the write produces.
 */
final readonly class CnameFile
{
    public const string NAME = 'CNAME';

    private const string TRAILING = "\n";

    private OutputTree $tree;

    /** @var non-empty-string */
    private string $hostname;

    /**
     * @param non-empty-string $hostname Configured site hostname, without a scheme or a path
     *
     * @throws InvalidArgumentException When the hostname cannot be a bare host
     */
    public function __construct(OutputTree $tree, string $hostname)
    {
        $bare = rtrim(
            string    : $hostname,
            characters: '/',
        );

        if ('' === $bare) {
            throw new InvalidArgumentException('The site hostname is empty.');
        }

        if (str_contains(
            haystack: $bare,
            needle  : '/',
        )) {
            throw new InvalidArgumentException(
                sprintf('The site hostname "%s" carries a path.', $hostname),
            );
        }

        $this->tree     = $tree;
        $this->hostname = $bare;
    }

    /**
     * The exact bytes the file holds, which is what the host reads and what verification compares.
     */
    public function contents(): string
    {
        return $this->hostname . self::TRAILING;
    }

    /**
     * @return non-empty-string
     */
    public function hostname(): string
    {
        return $this->hostname;
    }

    /**
     * @throws InvalidArgumentException When the tree refuses the file name
     * @throws RuntimeException         When the file cannot be read
     * @throws MissingCname            When the file is absent or does not hold the configured hostname
     */
    public function verify(): void
    {
        if (! $this->tree->exists(relativePath: self::NAME)) {
            throw MissingCname::absent();
        }

        $actual = $this->tree->read(relativePath: self::NAME);

        if (
            rtrim(
                string    : $actual,
                characters: self::TRAILING,
            ) !== $this->hostname
        ) {
            throw MissingCname::mismatched(
                expected: $this->hostname,
                actual  : $actual,
            );
        }
    }

    /**
     * @throws InvalidArgumentException When the tree refuses the file name
     * @throws RuntimeException         When the file cannot be written
     */
    public function write(): void
    {
        $this->tree->write(
            relativePath: self::NAME,
            contents    : $this->contents(),
        );
    }
}
