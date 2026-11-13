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

use function array_key_exists;
use function array_keys;
use function array_map;
use function in_array;
use function is_array;
use function is_string;

/**
 * One object of a decoded configuration file, together with the key path that reached it.
 *
 * The path is what makes a diagnostic actionable: every rejection reports the full key an operator
 * has to edit, whether that is `site.hostname` or `templates.map.page`, without any caller
 * assembling the path by hand. Accessors only read; presence, type and emptiness are the reader's
 * policy, expressed as the accessor it chooses.
 *
 * @internal
 */
final readonly class Section
{
    /**
     * @param array<array-key, mixed> $members
     */
    public function __construct(
        private array $members,
        private string $path,
    ) {}

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(static fn(int|string $key): string => (string) $key, array_keys($this->members));
    }

    /**
     * @throws InvalidSiteConfigException When the key is absent or does not hold an object
     */
    public function object(string $key): self
    {
        if (! array_key_exists($key, $this->members)) {
            throw InvalidSiteConfigException::at($this->pathFor($key), 'is required.');
        }

        /** @var mixed $value */
        $value = $this->members[$key];

        if (! is_array($value)) {
            throw InvalidSiteConfigException::at($this->pathFor($key), 'must be an object.');
        }

        return new self($value, $this->pathFor($key));
    }

    /**
     * @throws InvalidSiteConfigException When the key is present but does not hold an object
     */
    public function optionalObject(string $key): ?self
    {
        return array_key_exists($key, $this->members) ? $this->object($key) : null;
    }

    /**
     * @return non-empty-string|null
     *
     * @throws InvalidSiteConfigException When the key is present but does not hold a non-empty string
     */
    public function optionalString(string $key): ?string
    {
        return array_key_exists($key, $this->members) ? $this->string($key) : null;
    }

    /**
     * The configuration key a member of this section is addressed by.
     */
    public function pathFor(string $key): string
    {
        return 'site.json' === $this->path ? $key : "{$this->path}.{$key}";
    }

    /**
     * @param list<string> $allowed
     *
     * @throws InvalidSiteConfigException When the section carries a key the schema does not declare
     */
    public function rejectUnknownKeys(array $allowed): void
    {
        foreach (array_keys($this->members) as $key) {
            if (in_array((string) $key, $allowed, strict: true)) {
                continue;
            }

            throw InvalidSiteConfigException::at($this->pathFor((string) $key), 'is not a known key.');
        }
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidSiteConfigException When the key is absent or does not hold a non-empty string
     */
    public function string(string $key): string
    {
        if (! array_key_exists($key, $this->members)) {
            throw InvalidSiteConfigException::at($this->pathFor($key), 'is required.');
        }

        /** @var mixed $value */
        $value = $this->members[$key];

        if (! is_string($value) || '' === $value) {
            throw InvalidSiteConfigException::at($this->pathFor($key), 'must be a non-empty string.');
        }

        return $value;
    }
}
