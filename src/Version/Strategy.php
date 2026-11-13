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

namespace CommonDoc\Aggregate\Version;

use CommonDoc\Aggregate\Config\InvalidSiteConfig;

use function sprintf;

/**
 * How the versions of one project are discovered.
 *
 * The two members name the two corpora a project can present, and nothing else: a ref corpus (git
 * tags and branches) and a tree corpus (directory names inside the documentation directory).
 */
enum Strategy: string
{
    case Ref  = 'ref';
    case Tree = 'tree';

    /**
     * The declared default, used when `site.json` sets no strategy.
     */
    public static function default(): self
    {
        return self::Ref;
    }

    /**
     * @throws InvalidSiteConfig When the configured value names no member
     */
    public static function fromConfig(string $configured): self
    {
        return (
            self::tryFrom($configured) ?? throw InvalidSiteConfig::at(
                'versions.strategy',
                sprintf('"%s" is not one of "ref" or "tree".', $configured),
            )
        );
    }
}
