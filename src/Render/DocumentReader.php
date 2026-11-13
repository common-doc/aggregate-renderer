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
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParserInterface;

use function str_replace;

/**
 * Turns one document's source text into the shared parser's document tree.
 *
 * Line endings are normalised to LF before the source reaches the parser, so a checkout performed on
 * a platform that stores CRLF produces the same tree as one performed elsewhere. Everything else is
 * the parser's own work: this class exists so the renderer asks one question of one seam, and so the
 * markdown flavour stays a property of the injected parser rather than of the renderer.
 */
final readonly class DocumentReader
{
    public function __construct(
        private MarkdownParserInterface $parser,
    ) {}

    private static function withLf(string $source): string
    {
        return str_replace(
            search : ["\r\n", "\r"],
            replace: "\n",
            subject: $source,
        );
    }

    /**
     * @throws CommonMarkException When the source cannot be parsed
     */
    public function read(string $source): Document
    {
        return $this->parser->parse(self::withLf(source: $source));
    }
}
