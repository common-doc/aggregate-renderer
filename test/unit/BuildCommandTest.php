<?php

/*
 * This file is part of the common-doc/aggregate-renderer package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace CommonDocTest\Aggregate;

use CommonDoc\Aggregate\Build\ProjectBuild;
use CommonDoc\Aggregate\Build\SiteBuild;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\Console\BuildCommand;
use CommonDoc\Aggregate\Discovery\ProjectSourceInterface;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\StrategyFactory;
use CommonDocTest\Aggregate\Support\SiteRenderer;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function array_keys;
use function file_put_contents;
use function is_file;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(BuildCommand::class)]
#[CoversMethod(BuildCommand::class, 'configure')]
#[CoversMethod(BuildCommand::class, 'execute')]
final class BuildCommandTest extends TestCase
{
    private const array SEPARATE_STREAMS = ['capture_stderr_separately' => true];

    private TemporaryDirectory $directory;

    #[Test]
    public function exitsTwoWithADiagnosticAndNoReportWhenTheConfigurationIsMissing(): void
    {
        $tester = new CommandTester($this->command());

        $statusCode = $tester->execute(
            ['--site' => "{$this->directory->path}/absent.json"],
            self::SEPARATE_STREAMS,
        );

        static::assertSame(2, $statusCode);
        static::assertStringContainsString('cannot be read', $tester->getErrorOutput());
        static::assertStringContainsString('Invalid site configuration', $tester->getErrorOutput());
        static::assertSame('', $tester->getDisplay());
        static::assertFalse(is_file($this->reportPath()));
    }

    #[Test]
    public function printsASummaryAndExitsWithTheReportCode(): void
    {
        $tester = new CommandTester($this->command());

        $statusCode = $tester->execute(['--site' => $this->siteFile()], self::SEPARATE_STREAMS);

        static::assertSame(0, $statusCode);
        static::assertStringContainsString('0 pages in 0 projects', $tester->getDisplay());
        static::assertStringContainsString('Report written to', $tester->getDisplay());
        static::assertFileExists($this->reportPath());
    }

    #[Test]
    public function printsTheReportAsJsonWhenAsked(): void
    {
        $tester = new CommandTester($this->command());

        $tester->execute(['--site' => $this->siteFile(), '--json' => true], self::SEPARATE_STREAMS);

        $report = json_decode(
            json       : $tester->getDisplay(),
            associative: true,
            flags      : JSON_THROW_ON_ERROR,
        );

        static::assertIsArray($report);
        static::assertSame(0, $report['exitCode'] ?? null);
        static::assertSame(
            ['ok', 'partial', 'failed', 'skipped', 'pages'],
            array_keys($report['totals'] ?? []),
        );
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    private function command(): BuildCommand
    {
        $discovery = $this->createStub(ProjectSourceInterface::class);
        $discovery->method('all')->willReturn([]);

        $renderer = new SiteRenderer();

        return new BuildCommand(
            new SiteConfigReader(),
            new SiteBuild(
                discovery   : $discovery,
                renderer    : $renderer,
                projectBuild: new ProjectBuild(
                    $this->createStub(SourceInterface::class),
                    $renderer,
                    new StrategyFactory(),
                ),
            ),
        );
    }

    private function reportPath(): string
    {
        return "{$this->directory->path}/build/report.json";
    }

    private function siteFile(): string
    {
        $path = "{$this->directory->path}/site.json";

        file_put_contents($path, json_encode([
            'organization' => ['name' => 'common-doc', 'tokenEnvVar' => 'COMMONDOC_TOKEN'],
            'output'       => ['directory' => 'build', 'reportPath' => 'build/report.json'],
            'site'         => ['hostname' => 'docs.example.com'],
        ], JSON_THROW_ON_ERROR));

        return $path;
    }
}
