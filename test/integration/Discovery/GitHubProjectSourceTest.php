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

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Discovery\GitHubApi;
use CommonDoc\Aggregate\Discovery\GitHubProjectSource;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Discovery\Token;
use CommonDoc\Aggregate\Exception\DiscoveryException;
use JsonException;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

use function implode;
use function json_encode;
use function putenv;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * The contract test for the GitHub boundary.
 *
 * Every case here is a recorded answer, so what is asserted is the request the source builds and the
 * project list it derives from the answer, not the shape of a live endpoint. The four answers that
 * decide onboarding are covered deliberately: a repository with the property, one with an empty
 * value, one with no property at all, and a refused credential.
 */
#[CoversClass(GitHubApi::class)]
#[CoversMethod(GitHubApi::class, '__construct')]
#[CoversMethod(GitHubApi::class, 'find')]
#[CoversMethod(GitHubApi::class, 'get')]
#[CoversClass(GitHubProjectSource::class)]
#[CoversMethod(GitHubProjectSource::class, '__construct')]
#[CoversMethod(GitHubProjectSource::class, 'all')]
final class GitHubProjectSourceTest extends TestCase
{
    private const string TOKEN_VARIABLE = 'AGGREGATE_RENDERER_CONTRACT_TOKEN';

    private const string BEARER = 'contract-bearer-7';

    private const string ORGANIZATION = 'common-doc';

    private const string LISTING = 'https://api.github.com/orgs/common-doc/repos?per_page=100&page=1';

    private const string SECOND_PAGE = 'https://api.github.com/orgs/common-doc/repos?per_page=100&page=2';

    /**
     * @throws JsonException
     */
    #[Test]
    public function asksTheTwoEndpointsWithTheAgreedShape(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing($this->repository('commondoc'))),
            $this->response($this->properties($this->property('docs'))),
        );

        $this->source($client)->all();

        static::assertCount(2, $client->sent);

        static::assertSame('GET', $client->sent[0]->getMethod());
        static::assertSame(self::LISTING, (string) $client->sent[0]->getUri());
        static::assertSame(
            'https://api.github.com/repos/common-doc/commondoc/properties/values',
            (string) $client->sent[1]->getUri(),
        );
        static::assertSame('Bearer ' . self::BEARER, $client->sent[0]->getHeaderLine('Authorization'));
        static::assertSame('Bearer ' . self::BEARER, $client->sent[1]->getHeaderLine('Authorization'));
        static::assertSame('application/vnd.github+json', $client->sent[0]->getHeaderLine('Accept'));
        static::assertSame('common-doc/aggregate-renderer', $client->sent[0]->getHeaderLine('User-Agent'));
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function pagesThroughTheListingUntilAPageIsNotFull(): void
    {
        $fullPage = [];
        $recorded = [];

        for ($index = 1; $index <= 100; $index++) {
            $fullPage[] = $this->repository(sprintf('empty-target-%03d', $index));
        }

        $recorded[] = $this->response($this->listing(...$fullPage));
        $recorded[] = $this->response($this->listing($this->repository('commondoc')));

        for ($index = 1; $index <= 100; $index++) {
            $recorded[] = $this->response($this->properties($this->property('')));
        }

        $recorded[] = $this->response($this->properties($this->property('docs')));

        $client = new RecordedClient(...$recorded);

        $projects = $this->source($client)->all();

        static::assertCount(1, $projects);
        static::assertSame('commondoc', $projects[0]->name);
        static::assertCount(103, $client->sent);
        static::assertSame(self::SECOND_PAGE, (string) $client->sent[1]->getUri());
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function refusesAnAnswerThatIsNotAnArray(): void
    {
        $client = new RecordedClient($this->response('{"message":"Bad credentials"}'));

        try {
            $this->source($client)->all();

            static::fail('A body that is not a JSON array must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertStringContainsString('not a JSON array', $failed->getMessage());
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function refusesAnAnswerThatIsNotJson(): void
    {
        $client = new RecordedClient($this->response('<html>maintenance</html>'));

        try {
            $this->source($client)->all();

            static::fail('A body that is not JSON must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertSame(ReasonCode::DiscoveryUnavailable, $failed->reasonCode);
            static::assertStringContainsString('its answer is not JSON', $failed->getMessage());
            static::assertInstanceOf(JsonException::class, $failed->getPrevious());
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function refusesAnAnswerWhoseEntriesAreNotObjects(): void
    {
        $client = new RecordedClient($this->response('[1,2,3]'));

        try {
            $this->source($client)->all();

            static::fail('An entry that is not an object must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertStringContainsString('not a JSON array of objects', $failed->getMessage());
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function refusesAnUnreachableBoundary(): void
    {
        $client = new RecordedClient($this->response('{"message":"Server Error"}', 500));

        try {
            $this->source($client)->all();

            static::fail('An error status must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertSame(ReasonCode::DiscoveryUnavailable, $failed->reasonCode);
            static::assertStringContainsString('status 500', $failed->getMessage());
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function refusesARejectedCredential(): void
    {
        $client = new RecordedClient(
            $this->response('{"message":"Bad credentials"}', 401),
        );

        try {
            $this->source($client)->all();

            static::fail('A refused credential must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertSame(ReasonCode::RejectedToken, $failed->reasonCode);
            static::assertStringNotContainsString(self::BEARER, $failed->getMessage());
            static::assertCount(1, $client->sent);
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function refusesARepositoryThatCarriesNoName(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing('{"owner":{"login":"common-doc"}}')),
        );

        try {
            $this->source($client)->all();

            static::fail('A repository without a name must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertStringContainsString('carries no "name"', $failed->getMessage());
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function refusesARepositoryThatCarriesNoOwner(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing('{"name":"commondoc"}')),
        );

        try {
            $this->source($client)->all();

            static::fail('A repository without an owner must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertStringContainsString('carries no owner', $failed->getMessage());
        }
    }

    #[Test]
    public function refusesATransportThatFails(): void
    {
        $cause = $this->createStub(ClientExceptionInterface::class);

        $client = new RecordedClient($cause);

        try {
            $this->source($client)->all();

            static::fail('A transport failure must block the run.');
        } catch (DiscoveryException $failed) {
            static::assertSame(ReasonCode::DiscoveryUnavailable, $failed->reasonCode);
            static::assertSame($cause, $failed->getPrevious());
            static::assertStringContainsString('failed in transport', $failed->getMessage());
        }
    }

    #[Test]
    public function refusesToAskAnythingWhenNoTokenIsConfigured(): void
    {
        $client = new RecordedClient();
        $source = $this->source($client);

        putenv(self::TOKEN_VARIABLE);

        try {
            $source->all();

            static::fail('A missing token must block the run before any request.');
        } catch (DiscoveryException $failed) {
            static::assertSame(ReasonCode::MissingToken, $failed->reasonCode);
            static::assertCount(0, $client->sent);
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function reportsTheProjectOfARepositoryThatCarriesTheProperty(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing($this->repository('commondoc'))),
            $this->response($this->properties($this->property('docs'))),
        );

        $projects = $this->source($client)->all();

        static::assertCount(1, $projects);
        static::assertInstanceOf(Project::class, $projects[0]);
        static::assertSame('commondoc', $projects[0]->name);
        static::assertSame(self::ORGANIZATION, $projects[0]->owner);
        static::assertSame('commondoc', $projects[0]->sitePath);
        static::assertSame('docs', $projects[0]->docDirectory);
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function skipsARepositoryThatCarriesNoDocTargetProperty(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing($this->repository('no-target'))),
            $this->response($this->properties($this->property('docs', 'unrelated'))),
        );

        static::assertSame([], $this->source($client)->all());
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function skipsARepositoryWhosePropertiesCannotBeRead(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing($this->repository('no-properties'))),
            $this->response('{"message":"Not Found"}', 404),
        );

        static::assertSame([], $this->source($client)->all());
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function skipsARepositoryWhosePropertyValueIsEmpty(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing(
                $this->repository('empty-target'),
                $this->repository('commondoc'),
            )),
            $this->response($this->properties($this->property(''))),
            $this->response($this->properties($this->property('docs'))),
        );

        $projects = $this->source($client)->all();

        static::assertCount(1, $projects);
        static::assertSame('commondoc', $projects[0]->name);
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function trimsThePropertyValue(): void
    {
        $client = new RecordedClient(
            $this->response($this->listing($this->repository('commondoc'))),
            $this->response($this->properties($this->property('  docs/site  '))),
        );

        static::assertSame('docs/site', $this->source($client)->all()[0]->docDirectory);
    }

    protected function tearDown(): void
    {
        putenv(self::TOKEN_VARIABLE);

        parent::tearDown();
    }

    private function listing(string ...$repositories): string
    {
        return sprintf('[%s]', implode(',', $repositories));
    }

    private function properties(string $property): string
    {
        return sprintf('[%s]', $property);
    }

    /**
     * @throws JsonException
     */
    private function property(string $value, string $name = 'doc-target'): string
    {
        return (string) json_encode(
            ['property_name' => $name, 'value' => $value],
            JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @throws JsonException
     */
    private function repository(string $name, string $owner = self::ORGANIZATION): string
    {
        return (string) json_encode(
            ['name' => $name, 'owner' => ['login' => $owner]],
            JSON_THROW_ON_ERROR,
        );
    }

    private function response(string $body, int $status = 200): ResponseInterface
    {
        return new Response(new StreamFactory()->createStream($body), $status);
    }

    private function source(RecordedClient $client): GitHubProjectSource
    {
        putenv(self::TOKEN_VARIABLE . '=' . self::BEARER);

        return new GitHubProjectSource(
            api         : new GitHubApi(
                client  : $client,
                requests: new RequestFactory(),
                token   : new Token(self::TOKEN_VARIABLE),
            ),
            organization: self::ORGANIZATION,
        );
    }
}
