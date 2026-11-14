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

use CommonDoc\Aggregate\Exception\HttpException;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function array_keys;
use function str_contains;

/**
 * A PSR-18 client that answers from a table keyed by a URL fragment and records every request.
 *
 * A fragment that matches nothing raises, so a test that reaches an unexpected endpoint fails loudly
 * instead of silently returning an empty page. No socket is ever opened.
 */
final class StubHttpTransport implements ClientInterface
{
    /** @var list<array{url: string, headers: array<string, string>}> */
    public array $requests = [];

    /**
     * @param array<string, ResponseInterface> $responses keyed by a URL fragment, checked in order
     */
    public function __construct(
        private readonly array $responses,
    ) {}

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();

        $this->requests[] = ['url' => $url, 'headers' => $this->headersOf($request)];

        foreach ($this->responses as $fragment => $response) {
            if (str_contains($url, $fragment)) {
                return $response;
            }
        }

        throw new HttpException("No stub response is registered for {$url}");
    }

    /**
     * @return array<string, string>
     */
    private function headersOf(RequestInterface $request): array
    {
        $headers = [];

        foreach (array_keys($request->getHeaders()) as $name) {
            $headers[$name] = $request->getHeaderLine($name);
        }

        return $headers;
    }
}
