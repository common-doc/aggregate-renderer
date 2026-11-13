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

use CommonDoc\Aggregate\Config\SiteConfig;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Builds the GitHub project source one site configuration describes.
 *
 * The HTTP seams arrive from the container, because a consumer may need to swap the client for one
 * with its own transport, proxy or retry policy. The organization and the token variable come from
 * the configuration, because they belong to the site rather than to the process. Only the request
 * factory is needed: discovery reads two endpoints and writes nothing, so there is no request body to
 * build.
 */
final readonly class GitHubProjectSourceFactory implements ProjectSourceFactoryInterface
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requests,
    ) {}

    #[Override]
    public function create(SiteConfig $config): ProjectSourceInterface
    {
        return new GitHubProjectSource(
            api         : new GitHubApi(
                client  : $this->client,
                requests: $this->requests,
                token   : new Token($config->tokenEnvVar),
            ),
            organization: $config->organization,
        );
    }
}
