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

namespace CommonDocTest\Aggregate\Build;

use CommonDoc\Aggregate\Build\BuildReport;
use CommonDoc\Aggregate\Build\ProjectResult;
use CommonDoc\Aggregate\Build\Total;
use CommonDocTest\Aggregate\Support\Projects;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuildReport::class)]
#[CoversMethod(BuildReport::class, '__construct')]
final class BuildReportTest extends TestCase
{
    #[Test]
    public function rejectsAnExitCodeOutsideTheContract(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The exit code 3 is not one of 0, 1 or 2');

        $this->report(
            exitCode: 3,
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

    #[Test]
    public function rejectsBlockedExitCodeWithWrittenPages(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The exit code is 2 while 1 pages were written');

        $this->report(
            exitCode: 2,
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

    #[Test]
    public function rejectsCompleteExitCodeWithADefectiveProject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The exit code is 0 while 1 projects are partial or failed');

        $this->report(
            exitCode: 0,
            projects: [Projects::failed()],
            totals  : new Total(
                ok     : 0,
                partial: 0,
                failed : 1,
                skipped: 0,
                pages  : 0,
            ),
        );
    }

    #[Test]
    public function rejectsDefectiveExitCodeWithNoDefectiveProject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The exit code is 1 while no project is partial or failed');

        $this->report(
            exitCode: 1,
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

    #[Test]
    public function rejectsProjectsThatAreNotOrderedBySitePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a list ordered by site path');

        $this->report(
            exitCode: 0,
            projects: [Projects::ok(sitePath: 'commondoc'), Projects::ok(sitePath: 'aggregate-renderer')],
            totals  : new Total(
                ok     : 2,
                partial: 0,
                failed : 0,
                skipped: 0,
                pages  : 2,
            ),
        );
    }

    #[Test]
    public function rejectsTotalsThatDoNotSummariseTheProjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('do not summarise');

        $this->report(
            exitCode: 0,
            projects: [Projects::ok()],
            totals  : new Total(
                ok     : 2,
                partial: 0,
                failed : 0,
                skipped: 0,
                pages  : 1,
            ),
        );
    }

    #[Test]
    public function reportsABuildBlockedBeforeRendering(): void
    {
        $report = $this->report(
            exitCode: 2,
            projects: [Projects::skipped()],
            totals  : new Total(
                ok     : 0,
                partial: 0,
                failed : 0,
                skipped: 1,
                pages  : 0,
            ),
        );

        static::assertSame(2, $report->exitCode);
        static::assertSame(0, $report->totals->pages);
    }

    #[Test]
    public function reportsACompleteBuild(): void
    {
        $report = $this->report(
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

        static::assertSame(0, $report->exitCode);
        static::assertCount(1, $report->projects);
        static::assertSame(1, $report->totals->pages);
    }

    #[Test]
    public function reportsADefectiveCorpus(): void
    {
        $report = $this->report(
            exitCode: 1,
            projects: [Projects::ok(), Projects::partial(sitePath: 'commondoc')],
            totals  : new Total(
                ok     : 1,
                partial: 1,
                failed : 0,
                skipped: 0,
                pages  : 2,
            ),
        );

        static::assertSame(1, $report->exitCode);
    }

    #[Test]
    public function sortsTheProjectsBySitePath(): void
    {
        $report = $this->report(
            exitCode: 0,
            projects: [Projects::ok(sitePath: 'aggregate-renderer'), Projects::ok(sitePath: 'commondoc')],
            totals  : new Total(
                ok     : 2,
                partial: 0,
                failed : 0,
                skipped: 0,
                pages  : 2,
            ),
        );

        static::assertSame('aggregate-renderer', $report->projects[0]->sitePath);
        static::assertSame('commondoc', $report->projects[1]->sitePath);
    }

    /**
     * @param list<ProjectResult> $projects
     */
    private function report(int $exitCode, array $projects, Total $totals): BuildReport
    {
        return new BuildReport(
            exitCode: $exitCode,
            projects: $projects,
            totals  : $totals,
        );
    }
}
