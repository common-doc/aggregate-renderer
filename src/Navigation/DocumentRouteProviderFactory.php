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

use CommonDoc\Aggregate\Build\Corpus;
use CommonDoc\Aggregate\Config\NavigationConfig;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use Override;
use Webware\Router\RouteProviderInterface;

final readonly class DocumentRouteProviderFactory implements RouteProviderFactoryInterface
{
    public function __construct(
        private PageAddressFactory $addresses,
        private NavigationConfig $navigation,
    ) {}

    #[Override]
    public function create(Corpus $corpus): ?RouteProviderInterface
    {
        if (! $this->navigation->enabled) {
            return null;
        }

        return new DocumentRouteProvider(
            addresses: $this->addresses,
            corpus   : $corpus,
            navId    : $this->navigation->identifier,
        );
    }
}
