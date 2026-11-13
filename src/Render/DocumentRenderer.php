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

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Turns markdown source into the HTML a page is written from.
 *
 * Reading the source and rendering the tree are one step from the caller's point of view, so they are
 * one collaborator here rather than two parameters of every actor that renders a page. The markdown
 * environment is still built once, by the factory, and injected into both halves.
 */
final readonly class DocumentRenderer
{
    public function __construct(
        private DocumentReader $reader,
        private HtmlRenderer $html,
    ) {}

    /**
     * @throws CommonMarkException When the source cannot be parsed or the tree cannot be rendered
     */
    public function render(string $markdown): string
    {
        return (string) $this->html->renderDocument($this->reader->read($markdown));
    }
}
