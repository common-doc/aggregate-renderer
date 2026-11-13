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

namespace CommonDocTest\Aggregate\Render;

use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Output\OutputTree;
use CommonDoc\Aggregate\Render\DocumentReader;
use CommonDoc\Aggregate\Render\DocumentRenderer;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Render\TargetRenderer;
use CommonDoc\Aggregate\Render\UnaddressableDocument;
use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Target;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use CommonDocTest\Aggregate\Support\Config;
use CommonDocTest\Aggregate\Support\TemporaryDirectory;
use InvalidArgumentException;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Mezzio\Template\Exception\RenderingException;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function is_file;

#[CoversClass(TargetRenderer::class)]
#[CoversClass(UnaddressableDocument::class)]
#[CoversClass(DocumentReader::class)]
#[CoversMethod(TargetRenderer::class, '__construct')]
#[CoversMethod(TargetRenderer::class, 'render')]
#[CoversMethod(UnaddressableDocument::class, 'at')]
#[CoversMethod(DocumentReader::class, 'read')]
final class TargetRendererTest extends TestCase
{
    private TemporaryDirectory $directory;

    #[Test]
    public function announcesThePageOnTheSharedContextWhileItRenders(): void
    {
        $seen = [];

        $templates = $this->createStub(TemplateRendererInterface::class);
        $templates
            ->method('render')
            ->willReturnCallback(
                static function (string $name, $params = []) use (&$seen): string {
                    $seen[$name] = $params;

                    return '<main class="document">' . ($params['documentBody'] ?? '') . '</main>';
                },
            );

        $this->renderer(
            documents: ['getting_started.md' => "# Start\n"],
            templates: $templates,
        )->render(
            target: $this->target(),
            documents: ['getting_started.md'],
        );

        $page = $seen[ViewFactory::DOCUMENT]['page'] ?? null;

        static::assertInstanceOf(Page::class, $page);
        static::assertSame('Getting Started', $page->label);
        static::assertSame('aggregate-renderer', $page->projectName);
        static::assertSame('2.0', $page->versionLabel);
        static::assertSame(
            'https://docs.example.org/aggregate-renderer/2.0/getting_started/',
            $page->url,
        );
        static::assertStringContainsString('<h1>Start</h1>', $seen[ViewFactory::DOCUMENT]['documentBody'] ?? '');
    }

    #[Test]
    public function clearsThePageContextAfterARejectedPage(): void
    {
        $context   = new PageContext();
        $templates = $this->createStub(TemplateRendererInterface::class);
        $templates->method('render')->willThrowException(new RuntimeException('template defect'));

        $this->renderer(
            documents: ['index.md' => "# Home\n"],
            templates: $templates,
            pages    : $context,
        )->render(
            target: $this->target(),
            documents: ['index.md'],
        );

        static::assertNull($context->current());
    }

    #[Test]
    public function clearsThePageContextAfterARenderedPage(): void
    {
        $context = new PageContext();

        $this->renderer(
            documents: ['index.md' => "# Home\n"],
            pages    : $context,
        )->render(
            target: $this->target(),
            documents: ['index.md'],
        );

        static::assertNull($context->current());
    }

    #[Test]
    public function failsEveryDocumentWhenOneCannotBeAddressed(): void
    {
        $pages = $this->renderer(documents: ['index.md' => "# Home\n", '../escape.md' => "# Escape\n"])
            ->render(
                target: $this->target(),
                documents: ['index.md', '../escape.md'],
            );

        static::assertCount(2, $pages);

        foreach ($pages as $page) {
            static::assertSame(PageStatus::Failed, $page->status);
            static::assertSame(ReasonCode::RenderFailed, $page->reasonCode);
            static::assertFalse($page->fromCache);
        }

        static::assertFalse(is_file("{$this->directory->path}/build/aggregate-renderer/2.0/index.html"));
    }

    #[Test]
    public function labelsAPageAfterAHyphenatedStem(): void
    {
        static::assertSame('Getting Started', $this->labelOf('getting-started.md'));
    }

    #[Test]
    public function labelsAPageAfterItsStem(): void
    {
        static::assertSame('Getting Started', $this->labelOf('guide/getting_started.md'));
    }

    #[Test]
    public function labelsASectionIndexAfterItsDirectory(): void
    {
        static::assertSame('Guide', $this->labelOf('guide/index.md'));
    }

    #[Test]
    public function labelsTheRootDocumentAfterItsProject(): void
    {
        static::assertSame('aggregate-renderer', $this->labelOf('index.md'));
    }

    #[Test]
    public function ordersThePagesByAddressRatherThanByTheOrderTheSourceListedThem(): void
    {
        $pages = $this->renderer(documents: ['guide/beta.md' => "# Beta\n", 'guide/alpha.md' => "# Alpha\n"])
            ->render(
                target: $this->target(),
                documents: ['guide/beta.md', 'guide/alpha.md'],
            );

        static::assertSame(
            [
                'https://docs.example.org/aggregate-renderer/2.0/guide/alpha/',
                'https://docs.example.org/aggregate-renderer/2.0/guide/beta/',
            ],
            [$pages[0]->address, $pages[1]->address],
        );
        static::assertTrue(is_file("{$this->directory->path}/build/aggregate-renderer/2.0/guide/alpha/index.html"));
        static::assertTrue(is_file("{$this->directory->path}/build/aggregate-renderer/2.0/guide/beta/index.html"));
    }

    #[Test]
    public function reportsAnInvalidArgumentFromACollaboratorAsAFailedPage(): void
    {
        $templates = $this->createStub(TemplateRendererInterface::class);
        $templates->method('render')->willThrowException(new InvalidArgumentException('bad argument'));

        $pages = $this->renderer(
            documents: ['index.md' => "# Home\n"],
            templates: $templates,
        )->render(
            target: $this->target(),
            documents: ['index.md'],
        );

        static::assertSame(PageStatus::Failed, $pages[0]->status);
        static::assertSame(ReasonCode::RenderFailed, $pages[0]->reasonCode);
        static::assertFalse($pages[0]->fromCache);
    }

    #[Test]
    public function reportsATemplateContractExceptionAsAFailedPage(): void
    {
        $templates = $this->createStub(TemplateRendererInterface::class);
        $templates->method('render')->willThrowException(new RenderingException('no such template'));

        $pages = $this->renderer(
            documents: ['index.md' => "# Home\n"],
            templates: $templates,
        )->render(
            target: $this->target(),
            documents: ['index.md'],
        );

        static::assertSame(PageStatus::Failed, $pages[0]->status);
        static::assertSame(ReasonCode::RenderFailed, $pages[0]->reasonCode);
        static::assertFalse($pages[0]->fromCache);
    }

    #[Test]
    public function reportsATemplateFailureAsAFailedPageRatherThanThrowing(): void
    {
        $templates = $this->createStub(TemplateRendererInterface::class);
        $templates->method('render')->willThrowException(new RuntimeException('template defect'));

        $pages = $this->renderer(
            documents: ['index.md' => "# Home\n"],
            templates: $templates,
        )
            ->render(
                target: $this->target(),
                documents: ['index.md'],
            );

        static::assertSame(PageStatus::Failed, $pages[0]->status);
        static::assertSame(ReasonCode::RenderFailed, $pages[0]->reasonCode);
        static::assertFalse(is_file("{$this->directory->path}/build/aggregate-renderer/2.0/index.html"));
    }

    #[Test]
    public function returnsNoResultsForATargetWithoutDocuments(): void
    {
        static::assertSame(
            [],
            $this->renderer()->render(
                target: $this->target(),
                documents: [],
            ),
        );
    }

    #[Test]
    public function theExceptionNamesTheVersionAndTheDocumentThatCouldNotBeAddressed(): void
    {
        $exception = UnaddressableDocument::at($this->target(), '../escape.md');

        static::assertSame(ReasonCode::RenderFailed, $exception->reasonCode);
        static::assertStringContainsString('2.0', $exception->getMessage());
        static::assertStringContainsString('../escape.md', $exception->getMessage());
    }

    #[Test]
    public function writesEveryPageOfTheTargetUnderItsVersionDirectory(): void
    {
        $pages = $this->renderer(documents: ['guide/setup.md' => "# Setup\n\nTwo steps.\n"])
            ->render(
                target: $this->target(),
                documents: ['guide/setup.md'],
            );

        static::assertCount(1, $pages);
        static::assertSame(
            'https://docs.example.org/aggregate-renderer/2.0/guide/setup/',
            $pages[0]->address,
        );
        static::assertSame(PageStatus::Rendered, $pages[0]->status);
        static::assertNull($pages[0]->reasonCode);
        static::assertFalse($pages[0]->fromCache);
        static::assertStringContainsString(
            'Two steps.',
            $this->tree()->read('aggregate-renderer/2.0/guide/setup/index.html'),
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

    private function environment(
        PageContext $pages,
        ?TemplateRendererInterface $templates = null,
    ): EnvironmentInterface {
        return new ViewFactory()->environment(
            templates: $templates ?? new ViewFactory()->create(config: Config::site()),
            pages    : $pages,
        );
    }

    private function labelOf(string $documentPath): string
    {
        $seen = [];

        $templates = $this->createStub(TemplateRendererInterface::class);
        $templates
            ->method('render')
            ->willReturnCallback(
                static function (string $name, $params = []) use (&$seen): string {
                    $seen[$name] = $params;

                    return '';
                },
            );

        $this->renderer(
            documents: [$documentPath => "# Page\n"],
            templates: $templates,
        )
            ->render(
                target: $this->target(),
                documents: [$documentPath],
            );

        $page = $seen[ViewFactory::DOCUMENT]['page'] ?? null;

        static::assertInstanceOf(Page::class, $page);

        return $page->label;
    }

    /**
     * @param array<string, string> $documents
     */
    private function renderer(
        array $documents = [],
        ?TemplateRendererInterface $templates = null,
        ?PageContext $pages = null,
    ): TargetRenderer {
        $source = $this->createStub(SourceInterface::class);
        $source
            ->method('contents')
            ->willReturnCallback(
                static fn(Target $target, string $documentPath): string => $documents[$documentPath] ?? '',
            );

        return $this->targetRenderer(
            source   : $source,
            templates: $templates,
            pages    : $pages,
        );
    }

    private function target(): Target
    {
        return new Target(
            project: new Project(
                name        : 'aggregate-renderer',
                owner       : 'common-doc',
                sitePath    : 'aggregate-renderer',
                docDirectory: 'docs',
            ),
            version: '2.0',
        );
    }

    private function targetRenderer(
        SourceInterface $source,
        ?TemplateRendererInterface $templates = null,
        ?PageContext $pages = null,
    ): TargetRenderer {
        $pages       ??= new PageContext();
        $environment = $this->environment(
            pages    : $pages,
            templates: $templates,
        );

        return new TargetRenderer(
            tree     : $this->tree(),
            addresses: new PageAddressFactory(hostname: 'docs.example.org'),
            source   : $source,
            renderer : new DocumentRenderer(
                reader: new DocumentReader(parser: new MarkdownParser(environment: $environment)),
                html  : new HtmlRenderer(environment: $environment),
            ),
            pages    : $pages,
        );
    }

    private function tree(): OutputTree
    {
        return new OutputTree(root: "{$this->directory->path}/build");
    }
}
