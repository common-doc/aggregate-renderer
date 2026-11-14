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

namespace CommonDoc\Aggregate\Config;

use CommonDoc\Aggregate\Exception\InvalidSiteConfigException;
use InvalidArgumentException;

use function preg_match;
use function sprintf;

/**
 * Reads the navigation block of a site configuration.
 *
 * It is separate from the site reader because the block is a schema of its own: a consumer either
 * leaves it out, which publishes exactly the tree the build produced before navigation existed, or
 * states both halves of it. Refusing the name here rather than handing it to the value object keeps
 * the key path in the diagnostic.
 */
final class NavigationReader
{
    /**
     * @throws InvalidSiteConfigException When the configured identifier is not a single name
     * @throws InvalidArgumentException   When the navigation config rejects the name it was given
     */
    public static function read(Section $site): NavigationConfig
    {
        $navigation = $site->optionalObject('navigation');

        if (null === $navigation) {
            return new NavigationConfig();
        }

        $navigation->rejectUnknownKeys(['enabled', 'identifier']);

        $identifier = $navigation->optionalString('identifier') ?? NavigationConfig::DEFAULT_IDENTIFIER;

        if (preg_match(NavigationConfig::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw InvalidSiteConfigException::at(
                'site.navigation.identifier',
                sprintf(
                    '"%s" is not a name of letters, digits, hyphens and underscores.',
                    $identifier,
                ),
            );
        }

        return new NavigationConfig(
            enabled   : $navigation->optionalBool('enabled') ?? false,
            identifier: $identifier,
        );
    }
}
