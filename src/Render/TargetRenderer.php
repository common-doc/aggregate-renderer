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

namespace CommonDoc\Aggregate\Render;

use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Build\TargetCorpus;
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Exception\UnaddressableDocumentException;
use CommonDoc\Aggregate\Navigation\DocumentRouteProvider;
use CommonDoc\Aggregate\Navigation\PageNavigation;
use CommonDoc\Aggregate\Output\OutputTree;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Target;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use InvalidArgumentException;
use League\CommonMark\Exception\CommonMarkException;
use Mezzio\Template\Exception\ExceptionInterface;
use RuntimeException;

use function strcmp;
use function usort;

/**
 * Renders every page of one target into the output tree.
 *
 * This is the only class in the package that touches the document rendering seam, and it does so
 * through the published contract alone: the source text is parsed, the page is announced on the
 * shared page context, and the renderer returns the finished page. Nothing here knows which template
 * rendered it or whose layout wrapped it.
 *
 * A target is published whole or not at all. Every address is derived and every document is read
 * before the first page is written, so a document that cannot be addressed or read leaves no partial
 * version behind. A page that fails while rendering is reported rather than thrown, so one defective
 * document degrades its target and is counted instead of taking the build with it.
 */
final readonly class TargetRenderer
{
    public function __construct(
        private OutputTree $tree,
        private PageAddressFactory $addresses,
        private SourceInterface $source,
        private DocumentRenderer $renderer,
        private PageContext $pages,
        private ?LinkRewriter $links = null,
        private ?PageNavigation $navigation = null,
    ) {}

    /**
     * One failed result per document of the target.
     *
     * The address of a document whose address could not be derived is the document path itself, so
     * the report names the document that made the corpus defective instead of inventing an address
     * for it.
     *
     * @param list<non-empty-string> $documents
     *
     * @return list<PageResult>
     *
     * @throws InvalidArgumentException When a page result is asked for a status its reason code cannot carry
     */
    private static function failed(array $documents): array
    {
        $pages = [];

        foreach ($documents as $documentPath) {
            $pages[] = new PageResult(
                address   : $documentPath,
                status    : PageStatus::Failed,
                reasonCode: ReasonCode::RenderFailed,
                fromCache : false,
            );
        }

        return $pages;
    }

    /**
     * @return list<PageResult> Ordered by address, one result per document
     *
     * @throws InvalidArgumentException When a page result is asked for a status its reason code cannot carry
     */
    public function render(TargetCorpus $corpus): array
    {
        $target = $corpus->target;

        try {
            $addresses = $this->addressesOf($target, $corpus->documents);

            /** @var list<array{non-empty-string, PageAddress, string}> $prepared */
            $prepared = [];

            foreach ($addresses as $documentPath => $address) {
                $prepared[] = [$documentPath, $address, $this->source->contents($target, $documentPath)];
            }
        } catch (SourceUnavailableException|UnaddressableDocumentException) {
            return self::failed($corpus->documents);
        }

        $pages = [];

        foreach ($prepared as [$documentPath, $address, $text]) {
            $pages[] = $this->renderPage(
                target      : $target,
                address     : $address,
                documentPath: $documentPath,
                contents    : $text,
            );
        }

        usort($pages, static fn(PageResult $left, PageResult $right): int => strcmp(
            $left->address,
            $right->address,
        ));

        return $pages;
    }

    /**
     * The address of every document of the target, or nothing at all.
     *
     * @param list<non-empty-string> $documents
     *
     * @return array<non-empty-string, PageAddress>
     *
     * @throws UnaddressableDocumentException When one document cannot name a page
     */
    private function addressesOf(Target $target, array $documents): array
    {
        $addresses = [];

        foreach ($documents as $documentPath) {
            try {
                $addresses[$documentPath] = $this->addresses->forDocument(
                    target      : $target,
                    documentPath: $documentPath,
                );
            } catch (InvalidArgumentException) {
                throw UnaddressableDocumentException::at($target, $documentPath);
            }
        }

        return $addresses;
    }

    /**
     * @throws InvalidArgumentException When a page result is asked for a status its reason code cannot carry
     */
    private function renderPage(
        Target $target,
        PageAddress $address,
        string $documentPath,
        string $contents,
    ): PageResult {
        try {
            $this->pages->set(new Page(
                label       : PageLabel::for($target, $documentPath),
                projectName : $target->project->name,
                projectSlug : $target->project->sitePath,
                sourcePath  : $documentPath,
                url         : $address->address,
                versionLabel: $target->version,
            ));

            $this->navigation?->activate(DocumentRouteProvider::nameFor($target, $documentPath));

            try {
                $rendered = $this->renderer->render(markdown: $contents);
                $rendered = $this->links?->rewrite($rendered, $target, $documentPath) ?? $rendered;
            } finally {
                $this->pages->clear();
                $this->navigation?->deactivate();
            }

            $this->tree->write($address->file, $rendered);
        } catch (CommonMarkException|ExceptionInterface|InvalidArgumentException|RuntimeException) {
            return new PageResult(
                address   : $address->address,
                status    : PageStatus::Failed,
                reasonCode: ReasonCode::RenderFailed,
                fromCache : false,
            );
        }

        return new PageResult(
            address   : $address->address,
            status    : PageStatus::Rendered,
            reasonCode: null,
            fromCache : false,
        );
    }
}
