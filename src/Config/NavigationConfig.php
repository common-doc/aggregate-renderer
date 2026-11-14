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

use InvalidArgumentException;

use function preg_match;
use function sprintf;

/**
 * Whether the build publishes navigation, and which navigation it publishes it into.
 *
 * Navigation is opt in because it changes the published output: a page gains a sidebar, and every
 * relative link in it is rewritten to a route. A consumer that has not opted in gets exactly the tree
 * it got before, which is why the flag is read once here rather than probed wherever it matters.
 *
 * The identifier is the navigation a page belongs to. It is a name, not a path, so it is allowed to be
 * anything a single segment of a navigation tree can be called.
 */
final readonly class NavigationConfig
{
    public const string DEFAULT_IDENTIFIER = 'docs';

    public const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /** @var non-empty-string */
    public string $identifier;

    /**
     * @param non-empty-string $identifier
     *
     * @throws InvalidArgumentException When the identifier is empty or not a single name
     */
    public function __construct(
        public bool $enabled = false,
        string $identifier = self::DEFAULT_IDENTIFIER,
    ) {
        if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The navigation identifier "%s" is not a name of letters, digits, hyphens and underscores.',
                $identifier,
            ));
        }

        $this->identifier = $identifier;
    }
}
