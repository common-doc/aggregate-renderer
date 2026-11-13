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

use function preg_match;
use function sprintf;

/**
 * One onboarded repository, as discovery reports it.
 *
 * A repository is onboarded when its `doc-target` custom property carries a directory, so a project
 * always names the directory its sources live in. A repository without that property produces no
 * project at all rather than one with an empty directory.
 */
final readonly class Project
{
    /** @var non-empty-string */
    public string $name;

    /** @var non-empty-string */
    public string $owner;

    /** @var non-empty-string */
    public string $sitePath;

    /** @var non-empty-string */
    public string $docDirectory;

    /**
     * @throws InvalidArgumentException When a value violates the invariants above
     */
    public function __construct(
        string $name,
        string $owner,
        string $sitePath,
        string $docDirectory,
    ) {
        $this->name         = self::requireValue($name, 'repository name');
        $this->owner        = self::requireValue($owner, 'owner');
        $this->sitePath     = self::requireValue($sitePath, 'site path');
        $this->docDirectory = self::requireValue($docDirectory, 'documentation directory');

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $this->sitePath) !== 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Project "%s" carries the site path "%s", which is not in the derived form.',
                    $this->name,
                    $this->sitePath,
                ),
            );
        }
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When the value is empty
     */
    private static function requireValue(string $value, string $field): string
    {
        if ($value === '') {
            throw new InvalidArgumentException(
                sprintf('A discovered project requires a non-empty %s.', $field),
            );
        }

        return $value;
    }
}
