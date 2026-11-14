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

use Webware\Navigation\View\Helper\Navigation;

/**
 * The sidebar markup for the page currently being rendered.
 *
 * The tree building belongs to `Webware\Navigation`: this class only tells the helper which route is
 * current and which navigation the page belongs to, then reads the markup back. It holds no route
 * table and no tree of its own, so a page cannot be rendered against a navigation that disagrees with
 * the routes the same build published.
 *
 * The activation is explicit because the markup is produced while the layout renders, which happens
 * after the page body. A value read at that point cannot know which page is being rendered unless it
 * was recorded first, so the renderer records it and clears it afterwards.
 */
final class PageNavigation
{
    private ?string $activeRouteName = null;

    public function __construct(
        private readonly Navigation $helper,
        private readonly string $navId = 'docs',
    ) {}

    public function activate(string $routeName): void
    {
        $this->activeRouteName = $routeName;
    }

    public function deactivate(): void
    {
        $this->activeRouteName = null;
    }

    public function markup(): string
    {
        if (null === $this->activeRouteName) {
            return '';
        }

        $this->helper->setActiveRouteName($this->activeRouteName);

        /** @var array<string, mixed> $options */
        $options = [
            'type'  => 'sidebar',
            'navId' => $this->navId,
        ];

        return ($this->helper)($this->navId)->menu($options);
    }
}
