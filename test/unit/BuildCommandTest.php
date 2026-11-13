<?php

/*
 * This file is part of the common-doc/aggregate-renderer package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace CommonDocTest\Aggregate;

use CommonDoc\Aggregate\Build\SiteBuildFactory;
use CommonDoc\Aggregate\Config\SiteConfigReader;
use CommonDoc\Aggregate\Console\BuildCommand;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Discovery\ProjectSourceInterface;
use CommonDoc\Aggregate\Render\SiteRendererFactoryInterface;
use CommonDoc\Aggregate\Report\ReportFileFactory;
use CommonDoc\Aggregate\Source\MissingDocDirectory;
use CommonDoc\Aggregate\Source\SourceFactoryInterface;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Source\SourceUnavailable;
use CommonDoc\Aggregate\Version\StrategyFactory;
use CommonDoc\Aggregate\Version\Target;
use CommonDocTest\Aggregate\Support\SiteRenderer;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

use function array_keys;
use function array_map;
use function file_put_contents;
use function in_array;
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

    private static function project(string $sitePath): Project
    {
        return new Project(
            name        : $sitePath,
            owner       : 'common-doc',
            sitePath    : $sitePath,
            docDirectory: 'docs',
        );
    }

    #[Test]
    public function countsEveryProjectOfEveryStatusInTheSummary(): void
    {
        $tester = new CommandTester($this->command(
            projects   : ['alpha', 'beta', 'gamma'],
            candidates : ['alpha' => ['1.0.x'], 'beta' => ['1.0.x', '1.1.x']],
            unavailable: ['gamma'],
            missing    : ['1.1'],
        ));

        $statusCode = $tester->execute(['--site' => $this->siteFile()], self::SEPARATE_STREAMS);

        static::assertSame(1, $statusCode);
        static::assertStringContainsString(
            'site:build: 2 pages in 3 projects (1 ok, 1 partial, 1 failed, 0 skipped)',
            $tester->getDisplay(),
        );
    }

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

    #[Test]
    public function writesTheReportAsJsonWithASingleTrailingNewline(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add($this->command());

        $output = new BufferedOutput();

        $statusCode = $application->run(
            new ArrayInput(['command' => BuildCommand::NAME, '--site' => $this->siteFile(), '--json' => true]),
            $output,
        );

        $display = $output->fetch();

        static::assertSame(0, $statusCode);
        static::assertStringEndsWith("}\n", $display);
        static::assertStringEndsNotWith("\n\n", $display);
    }

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    /**
     * @param list<non-empty-string>                $projects
     * @param array<string, list<non-empty-string>> $candidates  By site path
     * @param list<non-empty-string>                $unavailable Site paths whose sources cannot be read
     * @param list<non-empty-string>                $missing     Versions whose documentation directory is absent
     */
    private function command(
        array $projects = [],
        array $candidates = [],
        array $unavailable = [],
        array $missing = [],
    ): BuildCommand {
        $discovery = $this->createStub(ProjectSourceInterface::class);
        $discovery->method('all')->willReturn(array_map(self::project(...), $projects));

        $renderers = $this->createStub(SiteRendererFactoryInterface::class);
        $renderers->method('create')->willReturn(new SiteRenderer());

        return new BuildCommand(
            new SiteConfigReader(),
            new SiteBuildFactory(
                discovery : $discovery,
                sources   : $this->sources($candidates, $unavailable, $missing),
                strategies: new StrategyFactory(),
                renderers : $renderers,
            ),
            new ReportFileFactory(),
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

    /**
     * @param array<string, list<non-empty-string>> $candidates
     * @param list<non-empty-string>                $unavailable
     * @param list<non-empty-string>                $missing
     */
    private function sources(array $candidates, array $unavailable, array $missing): SourceFactoryInterface
    {
        $source = $this->createStub(SourceInterface::class);

        $source
            ->method('candidates')
            ->willReturnCallback(
                static function (Project $project) use ($candidates, $unavailable): array {
                    if (in_array($project->sitePath, $unavailable, strict: true)) {
                        throw SourceUnavailable::for($project, 'the repository is unreachable');
                    }

                    return $candidates[$project->sitePath] ?? [];
                },
            );

        $source
            ->method('documents')
            ->willReturnCallback(
                static function (Target $target) use ($unavailable, $missing): array {
                    if (in_array($target->project->sitePath, $unavailable, strict: true)) {
                        throw SourceUnavailable::for($target->project, 'the repository is unreachable');
                    }

                    if (in_array($target->version, $missing, strict: true)) {
                        throw MissingDocDirectory::at($target->project, $target->version);
                    }

                    return ['index.md'];
                },
            );

        $sources = $this->createStub(SourceFactoryInterface::class);
        $sources->method('create')->willReturn($source);

        return $sources;
    }
}
