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

use CommonDoc\Aggregate\Version\Strategy;
use JsonException;

use function array_key_exists;
use function array_keys;
use function dirname;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function json_decode;
use function preg_match;
use function sprintf;
use function str_replace;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

/**
 * Reads the one configuration file the build uses.
 *
 * Validation happens here and nowhere else, so a consumer receives either a complete `SiteConfig` or
 * a diagnostic naming the key that is wrong. Nothing is defaulted by a consumer: the reader supplies
 * the defaults the schema declares.
 */
final class SiteConfigReader
{
    private const string TOKEN_ENV_VAR_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const string HOSTNAME_PATTERN = '/^[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?$/';

    private const string ABSOLUTE_PATH_PATTERN = '~^(?:[A-Za-z]:[\\\\/]|[\\\\/])~';

    private const string CACHE_DIRECTORY_DEFAULT = '.cache/multi-repo';

    /**
     * @throws InvalidSiteConfig When the file cannot be read or does not satisfy the schema
     */
    public function read(string $path): SiteConfig
    {
        $decoded = $this->decode($path);
        $base    = dirname($path);

        $this->rejectUnknownKeys($decoded, ['organization', 'output', 'site', 'versions', 'templates'], 'site.json');

        $organization = $this->section($decoded, 'organization', 'site.json', true);

        $this->rejectUnknownKeys($organization, ['name', 'tokenEnvVar'], 'organization');

        $name         = $this->nonEmptyString($this->required($organization, 'organization', 'name'), 'organization.name');
        $tokenEnvVar  = $this->nonEmptyString($this->required($organization, 'organization', 'tokenEnvVar'), 'organization.tokenEnvVar');

        if (preg_match(self::TOKEN_ENV_VAR_PATTERN, $tokenEnvVar) !== 1) {
            throw InvalidSiteConfig::at(
                'organization.tokenEnvVar',
                sprintf('"%s" is not an environment variable name.', $tokenEnvVar),
            );
        }

        $output = $this->section($decoded, 'output', 'site.json', true);

        $this->rejectUnknownKeys($output, ['directory', 'reportPath', 'cacheDirectory'], 'output');

        /** @var non-empty-string $outputDirectory */
        $outputDirectory = $this->resolved(
            $this->nonEmptyString($this->required($output, 'output', 'directory'), 'output.directory'),
            $base,
        );
        /** @var non-empty-string $reportPath */
        $reportPath      = $this->resolved(
            $this->nonEmptyString($this->required($output, 'output', 'reportPath'), 'output.reportPath'),
            $base,
        );
        /** @var non-empty-string $cacheDirectory */
        $cacheDirectory  = $this->resolved(
            $this->nonEmptyString(
                $this->optional($output, 'cacheDirectory') ?? self::CACHE_DIRECTORY_DEFAULT,
                'output.cacheDirectory',
            ),
            $base,
        );

        $site = $this->section($decoded, 'site', 'site.json', true);

        $this->rejectUnknownKeys($site, ['hostname'], 'site');

        $hostname = $this->nonEmptyString($this->required($site, 'site', 'hostname'), 'site.hostname');

        if (preg_match(self::HOSTNAME_PATTERN, $hostname) !== 1) {
            throw InvalidSiteConfig::at(
                'site.hostname',
                sprintf('"%s" is not a bare host name without a scheme or a slash.', $hostname),
            );
        }

        $strategy = $this->strategy($decoded);

        return new SiteConfig(
            $name,
            $tokenEnvVar,
            $outputDirectory,
            $reportPath,
            $cacheDirectory,
            $hostname,
            $strategy,
            $this->templateMap($decoded, $base),
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidSiteConfig When the file cannot be read or does not hold JSON
     */
    private function decode(string $path): array
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw InvalidSiteConfig::at('site.json', sprintf('"%s" cannot be read.', $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidSiteConfig::at('site.json', sprintf('"%s" is not valid JSON: %s', $path, $exception->getMessage()));
        }

        if (! is_array($decoded)) {
            throw InvalidSiteConfig::at('site.json', 'the file does not hold a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param array<array-key, mixed> $map
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidSiteConfig When a required section is absent or not an object
     */
    private function section(array $map, string $key, string $parent, bool $required): array
    {
        if (! array_key_exists($key, $map)) {
            if ($required) {
                throw InvalidSiteConfig::at($this->key($parent, $key), 'is required.');
            }

            return [];
        }

        /** @var mixed $value */
        $value = $map[$key];

        if (! is_array($value)) {
            throw InvalidSiteConfig::at($this->key($parent, $key), 'must be an object.');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $decoded
     *
     * @throws InvalidSiteConfig When the configured strategy is not a member
     */
    private function strategy(array $decoded): Strategy
    {
        $versions = $this->section($decoded, 'versions', 'site.json', false);

        if ($versions === []) {
            return Strategy::default();
        }

        $this->rejectUnknownKeys($versions, ['strategy'], 'versions');

        if (! array_key_exists('strategy', $versions)) {
            return Strategy::default();
        }

        return Strategy::fromConfig($this->nonEmptyString($versions['strategy'], 'versions.strategy'));
    }

    /**
     * @param array<array-key, mixed> $decoded
     *
     * @return array<string, non-empty-string>
     *
     * @throws InvalidSiteConfig When a mapped template is not a readable file
     */
    private function templateMap(array $decoded, string $base): array
    {
        $templates = $this->section($decoded, 'templates', 'site.json', false);

        if ($templates === []) {
            return [];
        }

        $this->rejectUnknownKeys($templates, ['map'], 'templates');

        $map = $this->section($templates, 'map', 'templates', false);

        if ($map === []) {
            return [];
        }

        $validated = [];

        foreach (array_keys($map) as $name) {
            $key          = $this->key('templates.map', (string) $name);
            $templateName = $this->nonEmptyString($name, $key);
            $templatePath = $this->nonEmptyString($map[$name] ?? null, $key);
            $resolved     = $this->resolved($templatePath, $base);

            if (! is_file($resolved) || ! is_readable($resolved)) {
                throw InvalidSiteConfig::at(
                    $key,
                    sprintf('"%s" is not a readable file.', $resolved),
                );
            }

            $validated[$templateName] = $templatePath;
        }

        return $validated;
    }

    /**
     * @param array<array-key, mixed> $map
     *
     * @throws InvalidSiteConfig When the key is absent
     */
    private function required(array $map, string $parent, string $key): mixed
    {
        if (! array_key_exists($key, $map)) {
            throw InvalidSiteConfig::at($this->key($parent, $key), 'is required.');
        }

        return $map[$key];
    }

    /**
     * @param array<array-key, mixed> $map
     */
    private function optional(array $map, string $key): mixed
    {
        return $map[$key] ?? null;
    }

    /**
     * @param array<array-key, mixed> $map
     * @param list<string> $allowed
     *
     * @throws InvalidSiteConfig When the object carries a key the schema does not declare
     */
    private function rejectUnknownKeys(array $map, array $allowed, string $parent): void
    {
        foreach (array_keys($map) as $key) {
            if (in_array((string) $key, $allowed, true)) {
                continue;
            }

            throw InvalidSiteConfig::at($this->key($parent, (string) $key), 'is not a known key.');
        }
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidSiteConfig When the value is not a non-empty string
     */
    private function nonEmptyString(mixed $value, string $key): string
    {
        if (! is_string($value) || $value === '') {
            throw InvalidSiteConfig::at($key, 'must be a non-empty string.');
        }

        return $value;
    }

    private function resolved(string $path, string $base): string
    {
        $joined = preg_match(self::ABSOLUTE_PATH_PATTERN, $path) === 1
            ? $path
            : $base . DIRECTORY_SEPARATOR . $path;

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $joined);
    }

    private function key(string $parent, string $key): string
    {
        return $parent === 'site.json' ? $key : $parent . '.' . $key;
    }
}
