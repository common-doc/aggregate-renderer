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
 * How many hrefs of each kind the build rewrote.
 *
 * The counters are carried on the report rather than folded into a project's status: a prose link
 * that names a document outside the corpus is a content defect the site owner has to see, but it is
 * not a page that failed to render, and turning it into one would make a deliberate link to a file
 * beside the documentation fail the build.
 *
 * A build creates one of these and every page adds to it, so the counts describe the run rather than
 * the last page rendered.
 */
final class LinkTotals
{
    /** @var array<string, non-negative-int> */
    private array $counts = [];

    public function __construct()
    {
        $this->reset();
    }

    public function add(LinkOutcome $outcome): void
    {
        $this->counts[$outcome->value] = $this->count($outcome) + 1;
    }

    /**
     * @return non-negative-int
     */
    public function count(LinkOutcome $outcome): int
    {
        return $this->counts[$outcome->value] ?? 0;
    }

    public function reset(): void
    {
        $this->counts = [];

        foreach (LinkOutcome::cases() as $outcome) {
            $this->counts[$outcome->value] = 0;
        }
    }

    /**
     * The counts keyed by wire value, in the order the enum declares them, so two runs over an
     * unchanged corpus serialise identically.
     *
     * @return array<string, non-negative-int>
     */
    public function toArray(): array
    {
        $counts = [];

        foreach (LinkOutcome::cases() as $outcome) {
            $counts[$outcome->value] = $this->count($outcome);
        }

        return $counts;
    }
}
