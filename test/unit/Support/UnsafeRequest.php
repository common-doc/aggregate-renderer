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

namespace CommonDocTest\Aggregate\Support;

use Laminas\Diactoros\Request;
use Override;
use Psr\Http\Message\UriInterface;

/**
 * A request carrying bytes a PSR-7 implementation refuses, so the transport's own boundary check
 * is exercised instead of the message implementation rejecting first.
 *
 * Only the two accessors the transport reads are overridden; nothing else is expected to consume it.
 */
final class UnsafeRequest extends Request
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private readonly UriInterface $unsafeUri,
        private readonly array $unsafeHeaders,
    ) {
        parent::__construct('http://example.test/', 'GET');
    }

    #[Override]
    public function getHeaders(): array
    {
        return $this->unsafeHeaders;
    }

    #[Override]
    public function getUri(): UriInterface
    {
        return $this->unsafeUri;
    }
}
