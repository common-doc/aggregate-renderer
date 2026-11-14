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

use Laminas\View\Helper\HelperInterface;

/**
 * The view helper the layout reads the sidebar markup from.
 *
 * A layout is a separate view model from the page it wraps, and the renderer only carries the body
 * across. A page therefore cannot hand the layout a variable: the layout has to ask for the value
 * itself, and a helper is the only thing that can be asked at render time. Reading it from
 * `PageNavigation` also means the layout cannot render a sidebar for a page that is not the page being
 * rendered.
 *
 * Nothing is emitted when navigation is disabled, so a site that has not opted in keeps its existing
 * markup byte for byte.
 */
final class NavigationMarkup implements HelperInterface
{
    /** The helper name the layout asks for, which is also the view method it calls. */
    public const string NAME = 'navigationMarkup';

    public function __construct(
        private readonly ?PageNavigation $navigation = null,
    ) {}

    public function __invoke(): string
    {
        return $this->navigation?->markup() ?? '';
    }
}
