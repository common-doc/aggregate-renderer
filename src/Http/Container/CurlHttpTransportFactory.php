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

use CommonDoc\Aggregate\Http\CurlHttpTransport;
use Psr\Container\ContainerInterface;

/**
 * Registers the package's own PSR-18 client as the default transport.
 *
 * The entry is keyed by the interface rather than aliased to the class, so a consumer that
 * registers {@see \Psr\Http\Client\ClientInterface} itself replaces this one wherever the
 * configuration is merged, and an instance registered before the first read wins outright.
 */
final class CurlHttpTransportFactory
{
    public function __invoke(ContainerInterface $_container): CurlHttpTransport
    {
        return new CurlHttpTransport();
    }
}
