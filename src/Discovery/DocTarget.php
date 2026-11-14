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
use function strtolower;
use function trim;

/**
 * The `doc-target` repository custom property, which is the whole onboarding surface.
 *
 * The property is defined at organization level as a `true_false` property, so the two accepted
 * forms are truth, which onboards the repository with the conventional `docs` directory, and a
 * non-empty string, which onboards it with the directory it names. The repository properties
 * endpoint serializes a `true_false` value as the strings "true" and "false" rather than as JSON
 * booleans, so both spellings are read as the boolean they stand for. Everything else, including
 * false and a blank string, leaves the repository off the site.
 *
 * @internal
 */
final class DocTarget
{
    public const string PROPERTY = 'doc-target';

    private const string DEFAULT_DIRECTORY = 'docs';

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

        if (true === $value) {
            return self::DEFAULT_DIRECTORY;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ('' === $trimmed) {
            return null;
        }

        return match (strtolower($trimmed)) {
            'true'  => self::DEFAULT_DIRECTORY,
            'false' => null,
            default => $trimmed,
        };
    }
}
