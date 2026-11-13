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

namespace CommonDoc\Aggregate\Discovery;

use CommonDoc\Aggregate\Exception\DiscoveryException;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use SensitiveParameter;

use function array_filter;
use function array_values;
use function is_array;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * The GitHub boundary, kept to the two things that touch it: sending a request and reading an answer.
 *
 * Every request is built from the injected PSR-17 factory and sent through the injected PSR-18 client,
 * which is what lets the contract test assert request shapes against recorded responses without a
 * vendor SDK. What an answer means is decided by the caller; this type only refuses it.
 *
 * @internal
 */
final readonly class GitHubApi
{
    private const string ACCEPT = 'application/vnd.github+json';

    private const string USER_AGENT = 'common-doc/aggregate-renderer';

    private const string AUTHORIZATION = 'Bearer ';

    private const int UNAUTHORIZED = 401;

    private const int NOT_FOUND = 404;

    private const int OK = 200;

    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requests,
        #[SensitiveParameter]
        private Token $token,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws DiscoveryException When the body is not a JSON array of objects
     */
    private static function objects(string $body, string $address): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $malformed) {
            throw DiscoveryException::unavailable($address, 'its answer is not JSON.', $malformed);
        }

        if (! is_array($decoded)) {
            throw DiscoveryException::unavailable($address, 'its answer is not a JSON array.');
        }

        if ([] !== array_filter($decoded, static fn(mixed $entry): bool => ! is_array($entry))) {
            throw DiscoveryException::unavailable($address, 'its answer is not a JSON array of objects.');
        }

        /** @var list<array<string, mixed>> */
        return array_values($decoded);
    }

    /**
     * Reads an address whose absence is meaningful, so a 404 is an answer rather than a failure.
     *
     * @return list<array<string, mixed>>|null Null when the address is absent
     *
     * @throws DiscoveryException When the answer is an error status or is not a JSON array of objects
     */
    public function find(string $address): ?array
    {
        return $this->answer($address);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws DiscoveryException When the answer is an error status or is not a JSON array of objects
     */
    public function get(string $address): array
    {
        $objects = $this->answer($address);

        if (null === $objects) {
            throw DiscoveryException::unavailable($address, 'it answered with status 404.');
        }

        return $objects;
    }

    /**
     * @return list<array<string, mixed>>|null Null when the address is absent
     *
     * @throws DiscoveryException When the answer is an error status or is not a JSON array of objects
     */
    private function answer(string $address): ?array
    {
        try {
            $request = $this->requests
                ->createRequest('GET', $address)
                ->withHeader('Accept', self::ACCEPT)
                ->withHeader('Authorization', self::AUTHORIZATION . $this->token->reveal())
                ->withHeader('User-Agent', self::USER_AGENT);
        } catch (InvalidArgumentException $unbuildable) {
            throw DiscoveryException::unavailable($address, 'the request could not be built.', $unbuildable);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $failure) {
            throw DiscoveryException::unavailable($address, 'the request failed in transport.', $failure);
        }

        $status = $response->getStatusCode();

        if (self::UNAUTHORIZED === $status) {
            throw DiscoveryException::rejectedToken($address);
        }

        if (self::NOT_FOUND === $status) {
            return null;
        }

        if (self::OK !== $status) {
            throw DiscoveryException::unavailable(
                $address,
                sprintf('it answered with status %d.', $status),
            );
        }

        return self::objects((string) $response->getBody(), $address);
    }
}
