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

namespace CommonDoc\Aggregate\Http\Container;

use Laminas\Diactoros\RequestFactory;
use Psr\Container\ContainerInterface;

/**
 * Registers diactoros' request factory as the default for the discovery seam.
 *
 * Keyed by the interface for the same reason as the transport default: a consumer can replace it
 * with its own message factory.
 */
final class RequestFactoryFactory
{
    public function __invoke(ContainerInterface $_container): RequestFactory
    {
        return new RequestFactory();
    }
}
