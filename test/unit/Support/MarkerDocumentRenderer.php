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

namespace CommonDocTest\Aggregate\Support;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * A document renderer that answers with a marker rather than a body.
 *
 * Registering one of these at a known priority against the renderer the view factory installs is how
 * a test proves which of two renderers for the document node actually wins.
 */
final readonly class MarkerDocumentRenderer implements NodeRendererInterface
{
    public function __construct(
        private string $markup,
    ) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        return $this->markup;
    }
}
