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

namespace CommonDocTestIntegration\Aggregate\Discovery;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function array_shift;

/**
 * A PSR-18 client that replays recorded responses in the order they are asked for.
 *
 * Discovery is the only boundary the package talks to over HTTP, and the point of the recorded client
 * is that a contract test asserts the request the source makes, not the parsing of a response the
 * source was handed directly. Every request is kept, so a test can assert both addresses and headers.
 */
final class RecordedClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $sent = [];

    /** @var list<ClientExceptionInterface|ResponseInterface> */
    private array $recorded;

    public function __construct(ClientExceptionInterface|ResponseInterface ...$recorded)
    {
        $this->recorded = [...$recorded];
    }

    /**
     * @throws ClientExceptionInterface When the recording holds a transport failure for this call
     * @throws RuntimeException When the recording runs out of answers
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;

        $next = array_shift($this->recorded);

        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next ?? throw new RuntimeException('The recording holds no response for this request.');
    }
}
