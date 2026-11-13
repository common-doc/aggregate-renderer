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

namespace CommonDoc\Aggregate\Source;

use CommonDoc\Aggregate\Config\SiteConfig;
use Override;

/**
 * Builds the git source one site configuration describes.
 *
 * The checkout and the reference reader are built once here and shared by both collaborators, so a
 * build lists a project's references and reads its documents against one cache rather than two. The
 * process wrapper is constructed here and nowhere else, which is what keeps every git read behind
 * the injected runner.
 */
final readonly class SourceFactory implements SourceFactoryInterface
{
    #[Override]
    public function create(SiteConfig $config): SourceInterface
    {
        $checkouts = new GitCheckout(
            runner        : new GitCommand(),
            cacheDirectory: $config->cacheDirectory,
        );
        $references = new GitReferences(checkouts: $checkouts);

        return new GitSource(
            candidates: new GitCandidates(
                checkouts : $checkouts,
                references: $references,
                strategy  : $config->strategy,
            ),
            documents: new GitDocuments(
                checkouts : $checkouts,
                references: $references,
                strategy  : $config->strategy,
            ),
        );
    }
}
