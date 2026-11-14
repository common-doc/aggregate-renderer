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

namespace CommonDocTest\Aggregate\Navigation;

use CommonDoc\Aggregate\Http\Middleware\NoOpMiddleware;
use CommonDoc\Aggregate\Navigation\DocsSidebarRenderer;
use CommonDoc\Aggregate\Navigation\NavigationMarkup;
use CommonDoc\Aggregate\Navigation\PageNavigation;
use CommonDoc\Aggregate\Navigation\RouteTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Navigation\View\Helper\Navigation;

use function preg_match_all;
use function substr_count;

#[CoversClass(PageNavigation::class)]
#[CoversClass(DocsSidebarRenderer::class)]
#[CoversClass(NavigationMarkup::class)]
#[CoversClass(RouteTable::class)]
#[CoversMethod(PageNavigation::class, 'activate')]
#[CoversMethod(PageNavigation::class, 'deactivate')]
#[CoversMethod(PageNavigation::class, 'markup')]
#[CoversMethod(DocsSidebarRenderer::class, 'render')]
#[CoversMethod(NavigationMarkup::class, '__invoke')]
final class PageNavigationTest extends TestCase
{
    #[Test]
    public function dropsAnItemWhoseParentWasNeverRegisteredToTheTopLevel(): void
    {
        $table = RouteTable::create();

        $this->route($table, 'p|aggregate-renderer|2.0|orphan', '/aggregate-renderer/2.0/orphan.html', [
            'label'  => 'Orphan',
            'parent' => 'p|aggregate-renderer|2.0|nowhere|index',
        ]);

        $navigation = $this->navigation($table);
        $navigation->activate('p|aggregate-renderer|2.0|orphan');

        $markup = $navigation->markup();

        static::assertSame(['Orphan'], $this->labelsOf($markup));
        static::assertStringNotContainsString('docs-nav-children', $markup);
    }

    #[Test]
    public function marksOnlyTheCurrentPageActive(): void
    {
        $table = RouteTable::create();

        $this->route($table, 'p|aggregate-renderer|2.0|index', '/aggregate-renderer/2.0/index.html', [
            'label' => 'Overview',
            'order' => 0,
        ]);
        $this->route($table, 'p|aggregate-renderer|2.0|guide|index', '/aggregate-renderer/2.0/guide/index.html', [
            'label'  => 'Guide',
            'order'  => 1,
            'parent' => 'p|aggregate-renderer|2.0|index',
        ]);
        $this->route($table, 'p|aggregate-renderer|2.0|guide|setup', '/aggregate-renderer/2.0/guide/setup.html', [
            'label'  => 'Setup',
            'order'  => 3,
            'parent' => 'p|aggregate-renderer|2.0|guide|index',
        ]);

        $navigation = $this->navigation($table);
        $navigation->activate('p|aggregate-renderer|2.0|guide|setup');

        $markup = $navigation->markup();

        static::assertSame(1, substr_count(
            haystack: $markup,
            needle  : 'aria-current="page"',
        ));
        static::assertStringContainsString(
            '<a class="docs-nav-link active" href="/aggregate-renderer/2.0/guide/setup.html"'
                . ' aria-current="page">Setup</a>',
            $markup,
        );
        static::assertStringContainsString(
            '<a class="docs-nav-link active" href="/aggregate-renderer/2.0/guide/index.html">Guide</a>',
            $markup,
        );
    }

    #[Test]
    public function nestsAChildUnderTheLandingPageNamedAsItsParent(): void
    {
        $table = RouteTable::create();

        $this->route($table, 'p|aggregate-renderer|2.0|index', '/aggregate-renderer/2.0/index.html', [
            'label' => 'Overview',
            'order' => 0,
        ]);
        $this->route($table, 'p|aggregate-renderer|2.0|guide|index', '/aggregate-renderer/2.0/guide/index.html', [
            'label'  => 'Guide',
            'order'  => 1,
            'parent' => 'p|aggregate-renderer|2.0|index',
        ]);
        $this->route($table, 'p|aggregate-renderer|2.0|guide|setup', '/aggregate-renderer/2.0/guide/setup.html', [
            'label'  => 'Setup',
            'order'  => 3,
            'parent' => 'p|aggregate-renderer|2.0|guide|index',
        ]);

        $navigation = $this->navigation($table);
        $navigation->activate('p|aggregate-renderer|2.0|guide|setup');

        $markup = $navigation->markup();

        static::assertSame(2, substr_count(
            haystack: $markup,
            needle  : 'docs-nav-children',
        ));
        static::assertStringContainsString(
            'href="/aggregate-renderer/2.0/guide/index.html">Guide</a><ul class="docs-nav-children">',
            $markup,
        );
    }

    #[Test]
    public function ordersTheTopLevelByTheOrderOption(): void
    {
        $table = RouteTable::create();

        $this->route($table, 'p|aggregate-renderer|2.0|zulu', '/aggregate-renderer/2.0/zulu.html', [
            'label' => 'Zulu',
            'order' => 5,
        ]);
        $this->route($table, 'p|aggregate-renderer|2.0|alpha', '/aggregate-renderer/2.0/alpha.html', [
            'label' => 'Alpha',
            'order' => 1,
        ]);

        $navigation = $this->navigation($table);
        $navigation->activate('p|aggregate-renderer|2.0|alpha');

        static::assertSame(['Alpha', 'Zulu'], $this->labelsOf($navigation->markup()));
    }

    #[Test]
    public function rendersNothingForARouteOfAnotherNavigation(): void
    {
        $table = RouteTable::create();

        $table->collector
            ->get('/aggregate-renderer/2.0/index.html', new NoOpMiddleware(), 'p|aggregate-renderer|2.0|index')
            ->setOptions([
                'navigation' => ['admin'],
                'label'      => 'Overview',
                'order'      => 0,
            ]);

        $navigation = $this->navigation($table);
        $navigation->activate('p|aggregate-renderer|2.0|index');

        static::assertSame('', $navigation->markup());
    }

    #[Test]
    public function rendersNothingWhileNoPageIsActive(): void
    {
        $table = RouteTable::create();

        $this->route($table, 'p|aggregate-renderer|2.0|index', '/aggregate-renderer/2.0/index.html', [
            'label' => 'Overview',
            'order' => 0,
        ]);

        $navigation = $this->navigation($table);

        static::assertSame('', $navigation->markup());

        $navigation->activate('p|aggregate-renderer|2.0|index');
        $navigation->deactivate();

        static::assertSame('', $navigation->markup());
    }

    #[Test]
    public function theLayoutHelperReadsTheMarkupOfThePageBeingRendered(): void
    {
        $table = RouteTable::create();

        $this->route($table, 'p|aggregate-renderer|2.0|index', '/aggregate-renderer/2.0/index.html', [
            'label' => 'Overview',
            'order' => 0,
        ]);

        $navigation = $this->navigation($table);
        $navigation->activate('p|aggregate-renderer|2.0|index');

        $helper = new NavigationMarkup($navigation);

        static::assertSame($navigation->markup(), $helper());
    }

    #[Test]
    public function theLayoutHelperRendersNothingWhenNavigationIsDisabled(): void
    {
        static::assertSame('', new NavigationMarkup()());
    }

    /**
     * @return list<string>
     */
    private function labelsOf(string $markup): array
    {
        preg_match_all('~<a class="[^"]*" href="[^"]*"(?: aria-current="page")?>([^<]+)</a>~', $markup, $matches);

        return $matches[1];
    }

    private function navigation(RouteTable $table): PageNavigation
    {
        return new PageNavigation(
            helper: new Navigation(
                routeCollector: $table->collector,
                menuRenderer  : new DocsSidebarRenderer(),
            ),
            navId : 'docs',
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function route(RouteTable $table, string $name, string $path, array $options): void
    {
        $table->collector
            ->get($path, new NoOpMiddleware(), $name)
            ->setOptions([
                'navigation' => ['docs'],
                ...$options,
            ]);
    }
}
