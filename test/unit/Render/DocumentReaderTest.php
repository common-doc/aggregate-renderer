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

use CommonDoc\Aggregate\Render\DocumentReader;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Parser\MarkdownParserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

#[CoversClass(DocumentReader::class)]
#[CoversMethod(DocumentReader::class, '__construct')]
#[CoversMethod(DocumentReader::class, 'read')]
final class DocumentReaderTest extends TestCase
{
    #[Test]
    public function normalisesEveryLineEndingToLfBeforeParsing(): void
    {
        $parser = $this->createMock(MarkdownParserInterface::class);
        $parser
            ->expects($this->once())
            ->method('parse')
            ->with("one\ntwo\n")
            ->willReturn(new Document());

        new DocumentReader(parser: $parser)->read("one\r\ntwo\r");
    }

    #[Test]
    public function parsesTheSourceWithTheInjectedParser(): void
    {
        $document = new DocumentReader(parser: new MarkdownParser(environment: $this->environment()))
            ->read("# Title\n\nBody\n");

        static::assertCount(2, iterator_to_array($document->children()));
    }

    #[Test]
    public function returnsTheTreeTheParserProduced(): void
    {
        $document = new Document();

        $parser = $this->createStub(MarkdownParserInterface::class);
        $parser->method('parse')->willReturn($document);

        static::assertSame($document, new DocumentReader(parser: $parser)->read('# Title'));
    }

    private function environment(): Environment
    {
        $environment = new Environment();
        $environment->addExtension(extension: new CommonMarkCoreExtension());

        return $environment;
    }
}
