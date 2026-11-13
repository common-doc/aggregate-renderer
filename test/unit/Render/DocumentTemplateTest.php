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

use CommonDoc\Aggregate\Render\ViewFactory;
use CommonDoc\View\Page;
use CommonDocTest\Aggregate\Support\Config;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function substr_count;

/**
 * The override proof: what this package's document template emits, and what the layout adds.
 *
 * commondoc renders every document through the address `commondoc::document`. This package replaces
 * that entry with its own file, so the two templates are asserted separately: the document must carry
 * the body region and nothing of the shell, the layout must carry the whole shell, and the composed
 * page must contain exactly one of each shell element. A count greater than one would mean the body
 * had emitted its own shell after all, which the composed assertion catches.
 */
#[CoversClass(ViewFactory::class)]
final class DocumentTemplateTest extends TestCase
{
    private const string BODY = '<p>Install the package.</p>';

    #[Test]
    public function theComposedPageCarriesOneShellAroundOneBody(): void
    {
        $html = $this->templates()->render(
            name: ViewFactory::DOCUMENT,
            params: ['documentBody' => self::BODY, 'page' => $this->page()],
        );

        static::assertSame(1, substr_count(
            haystack: $html,
            needle  : '<!DOCTYPE html>',
        ));
        static::assertSame(1, substr_count(
            haystack: $html,
            needle  : '<html lang="en">',
        ));
        static::assertSame(1, substr_count(
            haystack: $html,
            needle  : '<head>',
        ));
        static::assertSame(1, substr_count(
            haystack: $html,
            needle  : '<body class="aggregate">',
        ));
        static::assertStringContainsString('<main class="document">', $html);
    }

    #[Test]
    public function theDocumentTemplateEmitsTheBodyRegionOnly(): void
    {
        $html = $this->renderDocument();

        static::assertStringContainsString('<main class="document">', $html);
        static::assertStringContainsString(self::BODY, $html);
        static::assertStringNotContainsString('<!DOCTYPE', $html);
        static::assertStringNotContainsString('<html', $html);
        static::assertStringNotContainsString('<head', $html);
        static::assertStringNotContainsString('<body', $html);
    }

    #[Test]
    public function theLayoutEmitsTheShellTheDocumentLeavesOut(): void
    {
        $html = $this->templates()->render(
            name: ViewFactory::LAYOUT,
            params: ['content' => '<main class="document">' . self::BODY . '</main>', 'layout' => false],
        );

        static::assertStringContainsString('<!DOCTYPE html>', $html);
        static::assertStringContainsString('<html lang="en">', $html);
        static::assertStringContainsString('<head>', $html);
        static::assertStringContainsString('<body class="aggregate">', $html);
        static::assertStringContainsString(self::BODY, $html);
    }

    #[Test]
    public function theTitleOfThePageComesFromItsLabel(): void
    {
        $html = $this->templates()->render(
            name: ViewFactory::DOCUMENT,
            params: ['documentBody' => self::BODY, 'page' => $this->page()],
        );

        static::assertStringContainsString('<title>Getting Started</title>', $html);
    }

    private function page(): Page
    {
        return new Page(
            label       : 'Getting Started',
            projectName : 'aggregate-renderer',
            projectSlug : 'aggregate-renderer',
            sourcePath  : 'getting-started.md',
            url         : 'https://docs.example.org/aggregate-renderer/1.0/getting-started/',
            versionLabel: '1.0',
        );
    }

    private function renderDocument(): string
    {
        return $this->templates()->render(
            name: ViewFactory::DOCUMENT,
            params: ['documentBody' => self::BODY, 'page' => null, 'layout' => false],
        );
    }

    private function templates(): TemplateRendererInterface
    {
        return new ViewFactory()->create(config: Config::site());
    }
}
