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

use CommonDoc\Aggregate\Navigation\DocumentRouteProvider;
use CommonDoc\Aggregate\Navigation\PageNavigation;
use CommonDoc\Aggregate\Version\Target;
use CommonDoc\View\Page;
use CommonDoc\View\PageContext;
use League\CommonMark\Exception\CommonMarkException;

/**
 * Renders one page of markdown into the html that is written for it.
 *
 * The announcement, the two passes over the html and the clearing belong to one page and are kept
 * together here: a page that was announced but never cleared would be described by the next page's
 * layout, and a sidebar left active for a page that failed to render would be rendered onto the page
 * after it. Holding the three in one collaborator makes that lifetime a single fact instead of a rule
 * that the caller has to remember.
 */
final readonly class PagePresenter
{
    public function __construct(
        private DocumentRenderer $renderer,
        private PageContext $pages,
        private ?LinkRewriter $links = null,
        private ?PageNavigation $navigation = null,
    ) {}

    /**
     * The html of one page, with every internal link named by its published address.
     *
     * @param non-empty-string $documentPath
     *
     * @throws CommonMarkException When the markdown cannot be rendered
     */
    public function html(
        PageAddress $address,
        Target $target,
        string $documentPath,
        string $markdown,
    ): string {
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
            $rendered = $this->renderer->render(markdown: $markdown);

            return $this->links?->rewrite($rendered, $target, $documentPath) ?? $rendered;
        } finally {
            $this->pages->clear();
            $this->navigation?->deactivate();
        }
    }
}
