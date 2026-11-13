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

use function array_find;
use function is_string;
use function trim;

/**
 * The `doc-target` repository custom property, which is the whole onboarding surface: a repository
 * carrying a non-empty value is onboarded and a repository carrying anything else is not.
 *
 * @internal
 */
final class DocTarget
{
    public const string PROPERTY = 'doc-target';

    private function __construct() {}

    /**
     * @param list<array<string, mixed>> $properties the property list a repository answers with
     */
    public static function from(array $properties): ?string
    {
        $property = array_find(
            $properties,
            /** @param array<string, mixed> $entry */
            static fn(array $entry): bool => self::PROPERTY === ($entry['property_name'] ?? null),
        );

        if (null === $property) {
            return null;
        }

        /** @var mixed $value */
        $value = $property['value'] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
