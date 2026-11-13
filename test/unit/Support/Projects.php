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

namespace CommonDocTest\Aggregate\Support;

use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Build\ProjectResult;
use CommonDoc\Aggregate\Build\ProjectStatus;
use CommonDoc\Aggregate\Build\ReasonCode;

/**
 * Project results the report tests compare against, built once so each test says what it is about
 * rather than repeating the same statuses.
 */
final class Projects
{
    public const string SITE_PATH = 'aggregate-renderer';

    /**
     * @return non-empty-string
     */
    public static function address(string $sitePath = self::SITE_PATH): string
    {
        return "https://docs.example.org/{$sitePath}/1.0/";
    }

    public static function failed(string $sitePath = self::SITE_PATH): ProjectResult
    {
        return new ProjectResult(
            sitePath   : $sitePath,
            status     : ProjectStatus::Failed,
            pages      : [self::failedPage(self::address(sitePath: $sitePath))],
            reasonCodes: [ReasonCode::RenderFailed],
        );
    }

    public static function failedPage(string $address): PageResult
    {
        return new PageResult(
            address   : $address,
            status    : PageStatus::Failed,
            reasonCode: ReasonCode::RenderFailed,
            fromCache : false,
        );
    }

    public static function ok(string $sitePath = self::SITE_PATH): ProjectResult
    {
        return new ProjectResult(
            sitePath   : $sitePath,
            status     : ProjectStatus::Ok,
            pages      : [self::rendered(self::address(sitePath: $sitePath))],
            reasonCodes: [],
        );
    }

    public static function partial(string $sitePath = self::SITE_PATH): ProjectResult
    {
        return new ProjectResult(
            sitePath   : $sitePath,
            status     : ProjectStatus::Partial,
            pages      : [
                self::rendered(self::address(sitePath: $sitePath)),
                self::failedPage(self::address(sitePath: $sitePath) . 'guide/'),
            ],
            reasonCodes: [ReasonCode::RenderFailed],
        );
    }

    public static function rendered(string $address): PageResult
    {
        return new PageResult(
            address   : $address,
            status    : PageStatus::Rendered,
            reasonCode: null,
            fromCache : false,
        );
    }

    public static function skipped(string $sitePath = self::SITE_PATH): ProjectResult
    {
        return new ProjectResult(
            sitePath   : $sitePath,
            status     : ProjectStatus::Skipped,
            pages      : [],
            reasonCodes: [ReasonCode::MissingDocDirectory],
        );
    }
}
