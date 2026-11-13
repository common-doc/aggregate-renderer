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

namespace CommonDocTest\Aggregate\Discovery;

use CommonDoc\Aggregate\Discovery\GitHubProjectSourceFactory;
use CommonDoc\Aggregate\Exception\DiscoveryException;
use CommonDocTest\Aggregate\Support\Config;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

use function putenv;
use function str_contains;

#[CoversClass(GitHubProjectSourceFactory::class)]
#[CoversMethod(GitHubProjectSourceFactory::class, '__construct')]
#[CoversMethod(GitHubProjectSourceFactory::class, 'create')]
final class GitHubProjectSourceFactoryTest extends TestCase
{
    private const string TOKEN_VARIABLE = 'COMMONDOC_TOKEN';

    #[Test]
    public function listsTheOrganizationOfTheConfiguration(): void
    {
        putenv(self::TOKEN_VARIABLE . '=a-token');

        $client = $this->createMock(ClientInterface::class);
        $client
            ->expects($this->once())
            ->method('sendRequest')
            ->with(static::callback(static fn(RequestInterface $request): bool => str_contains(
                (string) $request->getUri(),
                '/orgs/common-doc/repos',
            )))
            ->willReturn(new Response(
                new StreamFactory()->createStream('[]'),
                200,
                ['Content-Type' => 'application/json'],
            ));

        $source = $this->factory($client)->create(Config::site());

        static::assertSame([], $source->all());
    }

    #[Test]
    public function readsTheTokenVariableOfTheConfigurationRatherThanAConstantOne(): void
    {
        putenv(self::TOKEN_VARIABLE);

        $source = $this->factory($this->createStub(ClientInterface::class))
            ->create(Config::site());

        try {
            $source->all();

            static::fail('A source without a token must not reach the boundary.');
        } catch (DiscoveryException $failed) {
            static::assertStringContainsString(self::TOKEN_VARIABLE, $failed->getMessage());
        }
    }

    private function factory(ClientInterface $client): GitHubProjectSourceFactory
    {
        return new GitHubProjectSourceFactory(
            client  : $client,
            requests: new RequestFactory(),
        );
    }
}
