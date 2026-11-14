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

namespace CommonDoc\Aggregate\Navigation;

use Override;
use Webware\Navigation\NavigationContainer;
use Webware\Navigation\NavigationItem;
use Webware\Navigation\Renderer\RendererInterface;

use function htmlspecialchars;
use function is_string;
use function sprintf;

use const ENT_QUOTES;

/**
 * The sidebar markup for a documentation site.
 *
 * The theme owns its markup because the inline renderer in `Webware\Navigation` emits Bootstrap
 * classes, and this site loads no Bootstrap: the markup is correct for another project and wrong for
 * this one, so it is replaced rather than restyled from outside. Everything else, the tree and which
 * entry is current, is still the package's job.
 */
final readonly class DocsSidebarRenderer implements RendererInterface
{
    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function render(NavigationContainer $container, array $options = []): string
    {
        $class = is_string($options['class'] ?? null) ? $options['class'] : 'docs-nav';
        $items = $container->getItems();

        if ([] === $items) {
            return '';
        }

        $html = sprintf('<nav class="docs-sidebar"><ul class="%s">', self::escape($class));

        foreach ($items as $item) {
            $html .= $this->item($container, $item);
        }

        return $html . '</ul></nav>';
    }

    private function item(NavigationContainer $container, NavigationItem $item): string
    {
        $active = $container->isActive($item);
        $classes = $active ? 'docs-nav-link active' : 'docs-nav-link';
        $current = $active ? ' aria-current="page"' : '';

        $html = sprintf(
            '<li class="docs-nav-item"><a class="%s" href="%s"%s>%s</a>',
            $classes,
            self::escape($item->route->getPath()),
            $current,
            self::escape($item->label),
        );

        if (! $item->hasChildren()) {
            return $html . '</li>';
        }

        $html .= '<ul class="docs-nav-children">';

        foreach ($item->getChildren() as $child) {
            $html .= $this->item($container, $child);
        }

        return $html . '</ul></li>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, encoding: 'UTF-8');
    }
}
