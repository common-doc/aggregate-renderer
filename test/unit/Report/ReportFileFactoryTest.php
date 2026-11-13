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
use CommonDoc\Aggregate\Build\Total;
use CommonDoc\Aggregate\Report\ReportFile;
use CommonDoc\Aggregate\Report\ReportFileFactory;
use CommonDoc\Aggregate\Report\ReportFileFactoryInterface;
use CommonDocTest\Aggregate\Support\Config;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportFileFactory::class)]
#[CoversMethod(ReportFileFactory::class, 'create')]
final class ReportFileFactoryTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Test]
    public function buildsAReportWriter(): void
    {
        $factory = new ReportFileFactory();

        static::assertInstanceOf(ReportFileFactoryInterface::class, $factory);
        static::assertInstanceOf(ReportFile::class, $factory->create(config: Config::site()));
    }

    #[Test]
    public function theWriterWritesAtTheConfiguredPath(): void
    {
        $path = "{$this->directory->path}/reports/report.json";

        new ReportFileFactory()
            ->create(config: Config::site(reportPath: $path))
            ->write(new BuildReport(
                exitCode: 0,
                projects: [],
                totals  : new Total(
                    ok     : 0,
                    partial: 0,
                    failed : 0,
                    skipped: 0,
                    pages  : 0,
                ),
            ));

        static::assertFileExists($path);
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }
}
