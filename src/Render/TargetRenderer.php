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
use CommonDoc\Aggregate\Exception\SourceUnavailableException;
use CommonDoc\Aggregate\Exception\UnaddressableDocumentException;
use CommonDoc\Aggregate\Output\OutputTree;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\Aggregate\Version\Target;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use InvalidArgumentException;
use League\CommonMark\Exception\CommonMarkException;
use Mezzio\Template\Exception\ExceptionInterface;
use RuntimeException;

use function str_replace;
use function strcmp;
use function strrpos;
use function substr;
use function ucwords;
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

    private static function humanise(string $stem): string
    {
        return ucwords(str_replace(
            search : ['-', '_'],
            replace: ' ',
            subject: $stem,
        ));
    }

    /**
     * The title a page is published under, derived from the document path rather than from its content.
     *
     * The derivation is part of the contract, not a convenience: a title read from the document would
     * change whenever the prose did, and the page address would not.
     */
    private static function labelFor(Target $target, string $documentPath): string
    {
        $stem = substr(
            string: $documentPath,
            offset: 0,
            length: -3,
        );
        $slash = strrpos(
            haystack: $stem,
            needle  : '/',
        );
        $last = false === $slash
            ? $stem
            : substr(
                string: $stem,
                offset: $slash + 1,
            );

        if ('index' !== $last) {
            return self::humanise(stem: $last);
        }

        if (false === $slash) {
            return $target->project->name;
        }

        $parent = substr(
            string: $stem,
            offset: 0,
            length: $slash,
        );
        $parentSlash = strrpos(
            haystack: $parent,
            needle  : '/',
        );
        $entry = false === $parentSlash
            ? $parent
            : substr(
                string: $parent,
                offset: $parentSlash + 1,
            );

        return self::humanise(stem: $entry);
    }

    /**
     * @param list<non-empty-string> $documents Ordered by path, as the source returned them
     *
     * @return list<PageResult> Ordered by address, one result per document
     *
     * @throws InvalidArgumentException When a page result is asked for a status its reason code cannot carry
     */
    public function render(Target $target, array $documents): array
    {
        try {
            $addresses = $this->addressesOf($target, $documents);

            /** @var list<array{non-empty-string, PageAddress, string}> $prepared */
            $prepared = [];

            foreach ($addresses as $documentPath => $address) {
                $prepared[] = [$documentPath, $address, $this->source->contents($target, $documentPath)];
            }
        } catch (SourceUnavailableException|UnaddressableDocumentException) {
            return self::failed($documents);
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
                label       : self::labelFor($target, $documentPath),
                projectName : $target->project->name,
                projectSlug : $target->project->sitePath,
                sourcePath  : $documentPath,
                url         : $address->address,
                versionLabel: $target->version,
            ));

            try {
                $rendered = $this->renderer->render(markdown: $contents);
            } finally {
                $this->pages->clear();
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
