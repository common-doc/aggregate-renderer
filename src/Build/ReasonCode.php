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

namespace CommonDoc\Aggregate\Build;

/**
 * The closed set of reasons a build can record.
 *
 * Every defect in a run must name one of these, which is what keeps `--json` stable enough to script
 * against and keeps the human output from inventing vocabulary per defect. Adding a member is a
 * change to the report contract, not an implementation detail.
 */
enum ReasonCode: string
{
    case InvalidSiteConfig    = 'invalid-site-config';
    case MissingToken         = 'missing-token';
    case RejectedToken        = 'rejected-token';
    case DiscoveryUnavailable = 'discovery-unavailable';
    case DuplicateSlug        = 'duplicate-slug';
    case DuplicateVersion     = 'duplicate-version';
    case DuplicatePageAddress = 'duplicate-page-address';
    case NoVersions           = 'no-versions';
    case MissingDocDirectory  = 'missing-doc-directory';
    case SourceUnavailable    = 'source-unavailable';
    case RenderFailed         = 'render-failed';
    case WriteFailed          = 'write-failed';
    case MissingCname         = 'missing-cname';
}
