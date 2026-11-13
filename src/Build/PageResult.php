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

use InvalidArgumentException;

use function sprintf;

/**
 * What happened to one page.
 *
 * A page names its public address rather than its output file, because the address is what the report
 * and the site index publish, and the file is derivable from it. The status and the reason code are
 * kept consistent here rather than at the call site: a rendered page carries no reason, and a failed
 * page always names one.
 */
final readonly class PageResult
{
    /**
     * @param string $address Public address of the page, checked below rather than assumed
     *
     * @throws InvalidArgumentException When the status and the reason code disagree
     */
    public function __construct(
        public string $address,
        public PageStatus $status,
        public ?ReasonCode $reasonCode,
        public bool $fromCache,
    ) {
        if ('' === $address) {
            throw new InvalidArgumentException('A page result requires a non-empty address.');
        }

        if (PageStatus::Rendered === $status && null !== $reasonCode) {
            throw new InvalidArgumentException(sprintf(
                'The rendered page "%s" carries the reason code "%s".',
                $address,
                $reasonCode->value,
            ));
        }

        if (PageStatus::Failed === $status && null === $reasonCode) {
            throw new InvalidArgumentException(sprintf(
                'The failed page "%s" carries no reason code.',
                $address,
            ));
        }
    }
}
