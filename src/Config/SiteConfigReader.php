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
use CommonDoc\Aggregate\Version\Strategy;
use InvalidArgumentException;
use JsonException;

use function dirname;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_readable;
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
     * @throws InvalidSiteConfigException When the file cannot be read or does not satisfy the schema
     * @throws InvalidArgumentException   When the navigation config rejects the name it was given
     */
    public function read(string $path): SiteConfig
    {
        $root = new Section($this->decode($path), 'site.json');
        $base = dirname($path);

        $root->rejectUnknownKeys(['organization', 'output', 'site', 'versions', 'templates']);

        $organization = $this->organization($root);
        $output       = $this->output($root, $base);
        $site         = $this->site($root);

        return new SiteConfig(
            organization   : $organization['name'],
            tokenEnvVar    : $organization['tokenEnvVar'],
            outputDirectory: $output['directory'],
            reportPath     : $output['reportPath'],
            cacheDirectory : $output['cacheDirectory'],
            hostname       : $site['hostname'],
            strategy       : $this->strategy($root),
            templateMap    : $this->templateMap($root, $base),
            navigation     : $site['navigation'],
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws InvalidSiteConfigException When the file cannot be read or does not hold JSON
     */
    private function decode(string $path): array
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if (false === $contents) {
            throw InvalidSiteConfigException::at('site.json', sprintf('"%s" cannot be read.', $path));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidSiteConfigException::at('site.json', sprintf(
                '"%s" is not valid JSON: %s',
                $path,
                $exception->getMessage(),
            ));
        }

        if (! is_array($decoded)) {
            throw InvalidSiteConfigException::at('site.json', reason: 'the file does not hold a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param non-empty-string $configured
     *
     * @return non-empty-string
     *
     * @throws InvalidSiteConfigException When the value is not a bare host name
     */
    private function hostname(string $configured): string
    {
        if (preg_match(self::HOSTNAME_PATTERN, $configured) !== 1) {
            throw InvalidSiteConfigException::at(
                'site.hostname',
                sprintf('"%s" is not a bare host name without a scheme or a slash.', $configured),
            );
        }

        return $configured;
    }

    /**
     * The organization block, read as the one pair it declares.
     *
     * @return array{name: non-empty-string, tokenEnvVar: non-empty-string}
     *
     * @throws InvalidSiteConfigException When the block is absent or a value is not acceptable
     */
    private function organization(Section $root): array
    {
        $organization = $root->object('organization');
        $organization->rejectUnknownKeys(['name', 'tokenEnvVar']);

        $name = $organization->string('name');

        return [
            'name'        => $name,
            'tokenEnvVar' => $this->tokenVariable($organization->string('tokenEnvVar')),
        ];
    }

    /**
     * The output block, with every path resolved against the directory site.json was read from.
     *
     * @return array{
     *     directory: non-empty-string,
     *     reportPath: non-empty-string,
     *     cacheDirectory: non-empty-string,
     * }
     *
     * @throws InvalidSiteConfigException When the block is absent or a path is not acceptable
     */
    private function output(Section $root, string $base): array
    {
        $output = $root->object('output');
        $output->rejectUnknownKeys(['directory', 'reportPath', 'cacheDirectory']);

        /** @var non-empty-string $directory */
        $directory = $this->resolved($output->string('directory'), $base);
        /** @var non-empty-string $reportPath */
        $reportPath = $this->resolved($output->string('reportPath'), $base);
        /** @var non-empty-string $cacheDirectory */
        $cacheDirectory = $this->resolved(
            $output->optionalString('cacheDirectory') ?? self::CACHE_DIRECTORY_DEFAULT,
            $base,
        );

        return [
            'directory'      => $directory,
            'reportPath'     => $reportPath,
            'cacheDirectory' => $cacheDirectory,
        ];
    }

    private function resolved(string $path, string $base): string
    {
        $joined = preg_match(self::ABSOLUTE_PATH_PATTERN, $path) === 1
            ? $path
            : $base . DIRECTORY_SEPARATOR . $path;

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $joined);
    }

    /**
     * The site block: the host name the pages are addressed by, and how navigation is rendered.
     *
     * @return array{hostname: non-empty-string, navigation: NavigationConfig}
     *
     * @throws InvalidSiteConfigException When the block is absent or a value is not acceptable
     * @throws InvalidArgumentException   When the navigation config rejects the name it was given
     */
    private function site(Section $root): array
    {
        $site = $root->object('site');
        $site->rejectUnknownKeys(['hostname', 'navigation']);

        return [
            'hostname'   => $this->hostname($site->string('hostname')),
            'navigation' => NavigationReader::read($site),
        ];
    }

    /**
     * @throws InvalidSiteConfigException When the configured strategy is not a member
     */
    private function strategy(Section $root): Strategy
    {
        $versions = $root->optionalObject('versions');

        if (null === $versions) {
            return Strategy::default();
        }

        $versions->rejectUnknownKeys(['strategy']);

        $configured = $versions->optionalString('strategy');

        return null === $configured ? Strategy::default() : Strategy::fromConfig($configured);
    }

    /**
     * @return array<string, non-empty-string>
     *
     * @throws InvalidSiteConfigException When a mapped template is not a readable file
     */
    private function templateMap(Section $root, string $base): array
    {
        $templates = $root->optionalObject('templates');

        if (null === $templates) {
            return [];
        }

        $templates->rejectUnknownKeys(['map']);

        $map = $templates->optionalObject('map');

        if (null === $map) {
            return [];
        }

        $validated = [];

        foreach ($map->keys() as $name) {
            $templatePath = $map->string($name);
            $resolved     = $this->resolved($templatePath, $base);

            if (! is_file($resolved) || ! is_readable($resolved)) {
                throw InvalidSiteConfigException::at(
                    $map->pathFor($name),
                    sprintf('"%s" is not a readable file.', $resolved),
                );
            }

            $validated[$name] = $templatePath;
        }

        return $validated;
    }

    /**
     * @param non-empty-string $configured
     *
     * @return non-empty-string
     *
     * @throws InvalidSiteConfigException When the value is not an environment variable name
     */
    private function tokenVariable(string $configured): string
    {
        if (preg_match(self::TOKEN_ENV_VAR_PATTERN, $configured) !== 1) {
            throw InvalidSiteConfigException::at(
                'organization.tokenEnvVar',
                sprintf('"%s" is not an environment variable name.', $configured),
            );
        }

        return $configured;
    }
}
