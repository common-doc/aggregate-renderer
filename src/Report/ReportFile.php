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

namespace CommonDoc\Aggregate\Report;

use CommonDoc\Aggregate\Build\BuildReport;
use CommonDoc\Aggregate\Build\ProjectResult;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

use function dirname;
use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Writes the build report to the configured path.
 *
 * The key order is spelled out here rather than inherited from whatever produced the report, because
 * a workflow diffs the file: `contracts/build-report.schema.json` fixes the names and a literal array
 * fixes the order, so two runs over an unchanged corpus write identical bytes. Nothing is written
 * that the run did not measure: no timestamp, no absolute path and no token.
 *
 * @type ErrorShape = array{reasonCode: string}
 * @type ProjectShape = array{slug: non-empty-string, status: 'ok'|'partial'|'failed'|'skipped', pages: int, errors: list<ErrorShape>}
 * @type TotalShape = array{ok: non-negative-int, partial: non-negative-int, failed: non-negative-int, skipped: non-negative-int, pages: non-negative-int}
 * @type ReportShape = array{exitCode: int, totals: TotalShape, projects: list<ProjectShape>}
 */
final readonly class ReportFile
{
    /** @var non-empty-string */
    private string $path;

    /**
     * @throws InvalidArgumentException When the path is empty
     */
    public function __construct(string $path)
    {
        if ('' === $path) {
            throw new InvalidArgumentException('The report path is empty.');
        }

        $this->path = $path;
    }

    /**
     * @throws JsonException When the report cannot be encoded
     */
    public function encode(BuildReport $report): string
    {
        return (
            json_encode(
                value: $this->shapeOf($report),
                flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n"
        );
    }

    /**
     * @throws JsonException    When the report cannot be encoded
     * @throws RuntimeException When the report cannot be written
     */
    public function write(BuildReport $report): void
    {
        $directory = dirname($this->path);

        if (
            ! is_dir($directory)
            && ! mkdir(
                directory  : $directory,
                permissions: 0o777,
                recursive  : true,
            )
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                sprintf('The report directory "%s" cannot be created.', $directory),
            );
        }

        if (false === file_put_contents($this->path, $this->encode($report))) {
            throw new RuntimeException(
                sprintf('The report cannot be written to "%s".', $this->path),
            );
        }
    }

    /**
     * @return ProjectShape
     */
    private function projectShapeOf(ProjectResult $project): array
    {
        $errors = [];

        foreach ($project->reasonCodes as $reasonCode) {
            $errors[] = ['reasonCode' => $reasonCode->value];
        }

        return [
            'slug'   => $project->sitePath,
            'status' => $project->status->value,
            'pages'  => $project->renderedPages(),
            'errors' => $errors,
        ];
    }

    /**
     * @return ReportShape
     */
    private function shapeOf(BuildReport $report): array
    {
        $projects = [];

        foreach ($report->projects as $project) {
            $projects[] = $this->projectShapeOf($project);
        }

        return [
            'exitCode' => $report->exitCode,
            'totals'   => [
                'ok'      => $report->totals->ok,
                'partial' => $report->totals->partial,
                'failed'  => $report->totals->failed,
                'skipped' => $report->totals->skipped,
                'pages'   => $report->totals->pages,
            ],
            'projects' => $projects,
        ];
    }
}
