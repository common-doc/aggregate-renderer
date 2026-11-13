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

namespace CommonDoc\Aggregate\Exception;

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Version\Target;
use RuntimeException;

use function sprintf;

/**
 * A document that no public address can name, so no page can be published for it.
 *
 * The address is derived from the document path, and a path that leaves the accepted character set
 * has no address, which makes it a defect of the corpus rather than of this build. It is raised
 * before any page of the target is written, so one unaddressable document cannot leave a version
 * half published.
 *
 * The reason code is carried rather than assumed by the caller, so the report cannot disagree with
 * what happened.
 */
final class UnaddressableDocumentException extends RuntimeException
{
    private function __construct(
        public readonly ReasonCode $reasonCode,
        string $reason,
    ) {
        parent::__construct($reason);
    }

    public static function at(Target $target, string $documentPath): self
    {
        return new self(ReasonCode::RenderFailed, sprintf(
            'The document "%s" of "%s" at "%s" cannot be given a published address.',
            $documentPath,
            $target->project->sitePath,
            $target->version,
        ));
    }
}
