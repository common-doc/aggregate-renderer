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

use CommonDoc\Aggregate\Output\OutputTree;
use InvalidArgumentException;
use Mezzio\Template\Exception\ExceptionInterface;
use Mezzio\Template\TemplateRendererInterface;
use RuntimeException;

/**
 * The page a static host serves for an address that resolves to nothing.
 *
 * It is written at the output root, because that is the only place a host looks, and it is not a page
 * of any project: it carries no project address, is not counted in the page totals and has no entry
 * in the report.
 */
final readonly class NotFoundPage
{
    private const string FILE = '404.html';

    public function __construct(
        private OutputTree $tree,
        private TemplateRendererInterface $templates,
    ) {}

    /**
     * @throws ExceptionInterface       When the page cannot be rendered
     * @throws InvalidArgumentException When the file is not relative to the output directory
     * @throws RuntimeException         When the page cannot be written
     */
    public function write(): void
    {
        $this->tree->write(
            relativePath: self::FILE,
            contents    : $this->templates->render(name: ViewFactory::NOT_FOUND),
        );
    }
}
