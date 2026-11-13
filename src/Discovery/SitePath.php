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

namespace CommonDoc\Aggregate\Discovery;

use InvalidArgumentException;

use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

/**
 * The addressable name a repository contributes to the site.
 *
 * Derived from the repository name, and never configured: onboarding a project costs exactly one
 * custom property, so the site path has to be something the repository already carries. Lowercasing
 * and collapsing every run outside `[a-z0-9]` into a single hyphen yields a name that needs no
 * encoding in a path or a URL, and that two repositories differing only in punctuation cannot share
 * by accident, because they derive the same path deliberately.
 */
final readonly class SitePath
{
    private const string SEPARATORS = '/[^a-z0-9]+/';

    /** @var non-empty-string */
    public string $value;

    /**
     * @param non-empty-string $value
     */
    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * @throws InvalidArgumentException When the repository name derives no path at all
     */
    public static function derive(string $repositoryName): self
    {
        $collapsed = preg_replace(
            pattern    : self::SEPARATORS,
            replacement: '-',
            subject    : strtolower($repositoryName),
        );
        $path = trim(
            string    : $collapsed ?? '',
            characters: '-',
        );

        if ('' === $path) {
            throw new InvalidArgumentException(
                sprintf('The repository name "%s" does not derive a site path.', $repositoryName),
            );
        }

        return new self($path);
    }
}
