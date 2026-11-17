<?php

/*
 * This file is part of the common-doc/aggregate-renderer package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace CommonDocTest\Aggregate\Build;

use CommonDoc\Aggregate\Build\Corpus;
use CommonDoc\Aggregate\Build\CorpusCollector;
use CommonDoc\Aggregate\Build\CorpusRegistration;
use CommonDoc\Aggregate\Build\LinkTotal;
use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Build\ProjectBuild;
use CommonDoc\Aggregate\Build\ProjectCorpus;
use CommonDoc\Aggregate\Build\ProjectOutcome;
use CommonDoc\Aggregate\Build\ProjectStatus;
use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Build\SiteBuild;
use CommonDoc\Aggregate\Build\TargetCorpus;
use CommonDoc\Aggregate\Config\NavigationConfig;
use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Discovery\ProjectSourceInterface;
use CommonDoc\Aggregate\Exception\MissingDocDirectoryException;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Navigation\DocumentRouteProviderFactory;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Strategy;
use CommonDoc\Aggregate\Version\StrategyFactory;
use CommonDoc\Aggregate\Version\Target;
use CommonDocTest\Aggregate\Support\SiteRenderer;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_put_contents;
use function in_array;
use function is_file;
use function mkdir;

#[CoversClass(SiteBuild::class)]
#[CoversClass(ProjectBuild::class)]
#[CoversClass(ProjectOutcome::class)]
#[CoversClass(Corpus::class)]
#[CoversClass(CorpusCollector::class)]
#[CoversClass(CorpusRegistration::class)]
#[CoversClass(DocumentRouteProviderFactory::class)]
#[CoversClass(LinkTotal::class)]
#[CoversClass(NavigationConfig::class)]
#[CoversClass(ProjectCorpus::class)]
#[CoversClass(TargetCorpus::class)]
#[CoversMethod(SiteBuild::class, 'build')]
#[CoversMethod(ProjectBuild::class, 'render')]
#[CoversMethod(ProjectOutcome::class, 'for')]
#[CoversMethod(ProjectOutcome::class, 'failed')]
#[CoversMethod(CorpusCollector::class, 'collect')]
#[CoversMethod(CorpusRegistration::class, 'resolve')]
#[CoversMethod(DocumentRouteProviderFactory::class, 'create')]
#[CoversMethod(LinkTotal::class, 'add')]
#[CoversMethod(LinkTotal::class, 'count')]
#[CoversMethod(NavigationConfig::class, '__construct')]
final class SiteBuildTest extends TestCase
{
    private TemporaryDirectory $directory;

    private static function page(string $address): PageResult
    {
        return new PageResult(
            address   : $address,
            status    : PageStatus::Rendered,
            reasonCode: null,
            fromCache : false,
        );
    }

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
    public function continuesToALaterVersionWhenAnEarlierOneIsAbsent(): void
    {
        $renderer = new SiteRenderer();

        $report = $this->build(
            projects  : ['widget'],
            candidates: ['widget' => ['1.0.x', '1.1.x']],
            renderer  : $renderer,
            missing   : ['1.0'],
        )->build($this->config());

        static::assertSame(ProjectStatus::Partial, $report->projects[0]->status);
        static::assertSame([ReasonCode::MissingDocDirectory], $report->projects[0]->reasonCodes);
        static::assertCount(1, $renderer->indexed);
        static::assertSame('1.1', $renderer->indexed[0]->version);
    }

    #[Test]
    public function countsAPartialProjectAndAFailedOneAsTwoDefects(): void
    {
        $report = $this->build(
            projects  : ['alpha', 'widget'],
            candidates: ['alpha' => ['1.0.x', '1.1.x']],
            renderer  : new SiteRenderer(),
            missing   : ['1.1'],
        )->build($this->config());

        static::assertSame(1, $report->totals->partial);
        static::assertSame(1, $report->totals->failed);
        static::assertSame(1, $report->exitCode);
    }

    #[Test]
    public function failsAProjectThatResolvesNoVersion(): void
    {
        $renderer = new SiteRenderer();

        $report = $this->build(
            projects  : ['widget'],
            candidates: [],
            renderer  : $renderer,
        )
            ->build($this->config());

        static::assertSame(1, $report->exitCode);
        static::assertSame(ProjectStatus::Failed, $report->projects[0]->status);
        static::assertSame([ReasonCode::NoVersion], $report->projects[0]->reasonCodes);
        static::assertSame([], $renderer->rendered);
        static::assertSame(1, $renderer->notFoundPages);
    }

    #[Test]
    public function failsAProjectWhenAPageCannotBeRendered(): void
    {
        $renderer = new SiteRenderer(static fn(TargetCorpus $corpus): array => [
            new PageResult(
                address   : 'https://docs.example.com/widget/1.0/index.html',
                status    : PageStatus::Failed,
                reasonCode: ReasonCode::RenderFailed,
                fromCache : false,
            ),
        ]);

        $report = $this->build(
            projects  : ['widget'],
            candidates: ['widget' => ['1.0.x']],
            renderer  : $renderer,
        )
            ->build($this->config());

        static::assertSame(1, $report->exitCode);
        static::assertSame(ProjectStatus::Failed, $report->projects[0]->status);
        static::assertSame([ReasonCode::RenderFailed], $report->projects[0]->reasonCodes);
    }

    #[Test]
    public function failsAProjectWhoseCandidatesCannotBeListed(): void
    {
        $report = $this->build(
            projects   : ['widget'],
            candidates : [],
            renderer   : new SiteRenderer(),
            unavailable: ['widget'],
        )->build($this->config());

        static::assertSame(1, $report->exitCode);
        static::assertSame([ReasonCode::SourceUnavailable], $report->projects[0]->reasonCodes);
    }

    #[Test]
    public function failsAProjectWhoseDocumentationDirectoryIsAbsent(): void
    {
        $renderer = new SiteRenderer();

        $report = $this->build(
            projects  : ['widget'],
            candidates: ['widget' => ['1.0.x']],
            renderer  : $renderer,
            missing   : ['1.0'],
        )->build($this->config());

        static::assertSame(1, $report->exitCode);
        static::assertSame([ReasonCode::MissingDocDirectory], $report->projects[0]->reasonCodes);
        static::assertSame([], $renderer->indexed);
    }

    #[Test]
    public function ordersProjectsBySitePath(): void
    {
        $report = $this->build(
            projects  : ['zeta', 'alpha'],
            candidates: ['zeta' => ['1.0.x'], 'alpha' => ['2.0.x']],
            renderer  : new SiteRenderer(),
        )->build($this->config());

        static::assertSame(0, $report->exitCode);
        static::assertSame(['alpha', 'zeta'], [
            $report->projects[0]->sitePath,
            $report->projects[1]->sitePath,
        ]);
    }

    #[Test]
    public function ordersThePagesOfAProjectByAddress(): void
    {
        $outcome = ProjectOutcome::for(
            project: self::project('widget'),
            targets: [],
            pages  : [
                self::page('https://docs.example.com/widget/1.0/guide/'),
                self::page('https://docs.example.com/widget/1.0/'),
            ],
            reasons: [],
        );

        static::assertSame(
            ['https://docs.example.com/widget/1.0/', 'https://docs.example.com/widget/1.0/guide/'],
            array_map(
                static fn(PageResult $page): string => $page->address,
                $outcome->result->pages,
            ),
        );
    }

    #[Test]
    public function ordersTheReasonsOfAProjectByTheirWireValue(): void
    {
        $outcome = ProjectOutcome::for(
            project: self::project('widget'),
            targets: [],
            pages  : [self::page('https://docs.example.com/widget/1.0/')],
            reasons: [ReasonCode::RenderFailed, ReasonCode::MissingDocDirectory],
        );

        static::assertSame(
            [ReasonCode::MissingDocDirectory, ReasonCode::RenderFailed],
            $outcome->result->reasonCodes,
        );
    }

    #[Test]
    public function reportsAPartialProjectWhenOneVersionRendersAndAnotherCannotBeRead(): void
    {
        $renderer = new SiteRenderer();

        $report = $this->build(
            projects  : ['widget'],
            candidates: ['widget' => ['1.0.x', '1.1.x']],
            renderer  : $renderer,
            missing   : ['1.1'],
        )->build($this->config());

        static::assertSame(1, $report->exitCode);
        static::assertSame(ProjectStatus::Partial, $report->projects[0]->status);
        static::assertSame([ReasonCode::MissingDocDirectory], $report->projects[0]->reasonCodes);
        static::assertSame(1, $report->totals->partial);
        static::assertCount(1, $renderer->indexed);
        static::assertSame('1.0', $renderer->indexed[0]->version);
    }

    #[Test]
    public function resetsTheOutputTreeBeforeWriting(): void
    {
        mkdir(
            directory: "{$this->directory->path}/site",
            recursive: true,
        );
        file_put_contents(
            filename: "{$this->directory->path}/site/stale.html",
            data    : 'stale',
        );

        $this->build(
            projects  : ['widget'],
            candidates: ['widget' => ['1.0.x']],
            renderer  : new SiteRenderer(),
        )
            ->build($this->config());

        static::assertFalse(is_file("{$this->directory->path}/site/stale.html"));
    }

    #[Test]
    public function writesAReportAndExitsZeroForACompleteCorpus(): void
    {
        $renderer = new SiteRenderer();

        $report = $this->build(
            projects  : ['widget'],
            candidates: ['widget' => ['1.0.x']],
            renderer  : $renderer,
        )
            ->build($this->config());

        static::assertSame(0, $report->exitCode);
        static::assertSame(1, $report->totals->ok);
        static::assertSame(1, $report->totals->pages);
        static::assertSame(ProjectStatus::Ok, $report->projects[0]->status);
        static::assertSame(
            'https://docs.example.com/widget/1.0/index.html',
            $report->projects[0]->pages[0]->address,
        );
    }

    #[Test]
    public function writesTheExtrasAndTheSiteAddressFile(): void
    {
        $renderer = new SiteRenderer();

        $this->build(
            projects  : ['widget'],
            candidates: ['widget' => ['1.0.x']],
            renderer  : $renderer,
        )
            ->build($this->config());

        static::assertSame(1, $renderer->notFoundPages);
        static::assertCount(1, $renderer->indexed);
        static::assertFileExists("{$this->directory->path}/site/CNAME");
        static::assertFileExists("{$this->directory->path}/report.json");
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
    private function build(
        array $projects,
        array $candidates,
        SiteRenderer $renderer,
        array $unavailable = [],
        array $missing = [],
    ): SiteBuild {
        $discovery = $this->createStub(ProjectSourceInterface::class);
        $discovery->method('all')->willReturn(array_map(self::project(...), $projects));

        $source = $this->source($candidates, $unavailable, $missing);
        $links  = new LinkTotal();

        return new SiteBuild(
            discovery   : $discovery,
            corpus      : new CorpusRegistration(
                corpus   : new CorpusCollector(
                    source    : $source,
                    strategies: new StrategyFactory(),
                ),
                providers: new DocumentRouteProviderFactory(
                    addresses : new PageAddressFactory(hostname: 'docs.example.com'),
                    navigation: new NavigationConfig(),
                ),
                routes   : RouteTable::create(),
            ),
            renderer    : $renderer,
            projectBuild: new ProjectBuild(
                renderer: $renderer,
                links   : $links,
            ),
            links       : $links,
        );
    }

    private function config(): SiteConfig
    {
        return new SiteConfig(
            organization   : 'common-doc',
            tokenEnvVar    : 'DOCS_TOKEN',
            outputDirectory: "{$this->directory->path}/site",
            reportPath     : "{$this->directory->path}/report.json",
            cacheDirectory : "{$this->directory->path}/cache",
            hostname       : 'docs.example.com',
            strategy       : Strategy::Ref,
            templateMap    : [],
        );
    }

    /**
     * @param array<string, list<non-empty-string>> $candidates
     * @param list<non-empty-string>                $unavailable
     * @param list<non-empty-string>                $missing
     */
    private function source(array $candidates, array $unavailable, array $missing): SourceInterface
    {
        $source = $this->createStub(SourceInterface::class);

        $source
            ->method('candidates')
            ->willReturnCallback(
                static function (Project $project) use ($candidates, $unavailable): array {
                    if (in_array($project->sitePath, $unavailable, strict: true)) {
                        throw SourceUnavailableException::for($project, reason: 'the repository is unreachable');
                    }

                    return $candidates[$project->sitePath] ?? [];
                },
            );

        $source
            ->method('documents')
            ->willReturnCallback(
                static function (Target $target) use ($unavailable, $missing): array {
                    if (in_array($target->project->sitePath, $unavailable, strict: true)) {
                        throw SourceUnavailableException::for(
                            $target->project,
                            reason: 'the repository is unreachable',
                        );
                    }

                    if (in_array($target->version, $missing, strict: true)) {
                        throw MissingDocDirectoryException::at($target->project, $target->version);
                    }

                    return ['index.md'];
                },
            );

        return $source;
    }
}
