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

namespace CommonDocTest\Aggregate\Report;

use CommonDoc\Aggregate\Build\BuildReport;
use CommonDoc\Aggregate\Build\ProjectResult;
use CommonDoc\Aggregate\Build\ProjectStatus;
use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Build\Total;
use CommonDoc\Aggregate\Report\ReportFile;
use CommonDocTest\Aggregate\Support\Projects;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_get_contents;
use function glob;
use function implode;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(ReportFile::class)]
#[CoversMethod(ReportFile::class, '__construct')]
#[CoversMethod(ReportFile::class, 'encode')]
#[CoversMethod(ReportFile::class, 'write')]
final class ReportFileTest extends TestCase
{
    private string $directory;

    #[Test]
    public function createsTheDirectoryThatHoldsTheReport(): void
    {
        $path = "{$this->directory}/build/reports/report.json";

        $this->reportFile($path)->write($this->completeReport());

        static::assertFileExists($path);
    }

    #[Test]
    public function encodesTheContractShape(): void
    {
        $encoded = $this->reportFile("{$this->directory}/report.json")->encode($this->completeReport());

        $expected =
            implode("\n", [
                '{',
                '    "exitCode": 0,',
                '    "totals": {',
                '        "ok": 1,',
                '        "partial": 0,',
                '        "failed": 0,',
                '        "skipped": 0,',
                '        "pages": 1',
                '    },',
                '    "projects": [',
                '        {',
                '            "slug": "aggregate-renderer",',
                '            "status": "ok",',
                '            "pages": 1,',
                '            "errors": []',
                '        }',
                '    ]',
                '}',
            ]) . "\n";

        static::assertSame($expected, $encoded);
    }

    #[Test]
    public function recordsEveryReasonCodeInOrder(): void
    {
        $project = new ProjectResult(
            sitePath   : 'aggregate-renderer',
            status     : ProjectStatus::Partial,
            pages      : [
                Projects::rendered(Projects::address()),
                Projects::failedPage(Projects::address() . 'guide/'),
            ],
            reasonCodes: [ReasonCode::RenderFailed, ReasonCode::WriteFailed],
        );

        $encoded = $this->reportFile("{$this->directory}/report.json")->encode(new BuildReport(
            exitCode: 1,
            projects: [$project],
            totals  : new Total(
                ok     : 0,
                partial: 1,
                failed : 0,
                skipped: 0,
                pages  : 1,
            ),
        ));

        static::assertStringContainsString(
            implode("\n", [
                '            "status": "partial",',
                '            "pages": 1,',
                '            "errors": [',
                '                {',
                '                    "reasonCode": "render-failed"',
                '                },',
                '                {',
                '                    "reasonCode": "write-failed"',
                '                }',
                '            ]',
            ]),
            $encoded,
        );
    }

    #[Test]
    public function rejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The report path is empty');

        new ReportFile(path: '');
    }

    #[Test]
    public function writesIdenticalBytesForIdenticalRuns(): void
    {
        $path = "{$this->directory}/report.json";
        $file = $this->reportFile($path);

        $file->write($this->completeReport());
        $first = file_get_contents($path);

        $file->write($this->completeReport());

        static::assertSame($first, file_get_contents($path));
    }

    #[Test]
    public function writesTheReportWithATrailingNewline(): void
    {
        $path = "{$this->directory}/report.json";
        $file = $this->reportFile($path);

        $file->write($this->completeReport());

        $contents = file_get_contents($path);

        static::assertIsString($contents);
        static::assertSame($file->encode($this->completeReport()), $contents);
        static::assertStringEndsWith("}\n", $contents);
    }

    protected function setUp(): void
    {
        $this->directory =
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aggregate-renderer-report-' . bin2hex(random_bytes(8));

        static::assertTrue(mkdir(
            directory  : $this->directory,
            permissions: 0o777,
            recursive  : true,
        ));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $this->removeDirectory($this->directory);
        }
    }

    private function completeReport(): BuildReport
    {
        return new BuildReport(
            exitCode: 0,
            projects: [Projects::ok()],
            totals  : new Total(
                ok     : 1,
                partial: 0,
                failed : 0,
                skipped: 0,
                pages  : 1,
            ),
        );
    }

    private function removeDirectory(string $directory): void
    {
        $entries = glob($directory . DIRECTORY_SEPARATOR . '*');

        foreach (false === $entries ? [] : $entries as $entry) {
            if (is_dir($entry)) {
                $this->removeDirectory($entry);

                continue;
            }

            unlink($entry);
        }

        rmdir($directory);
    }

    private function reportFile(string $path): ReportFile
    {
        return new ReportFile(path: $path);
    }
}
