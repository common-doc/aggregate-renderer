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
 * The five counters the report publishes.
 *
 * Counters are non-negative by construction, so no consumer has to defend against a negative total
 * appearing in a report that a workflow reads.
 */
final readonly class Total
{
    /** @var non-negative-int */
    public int $ok;

    /** @var non-negative-int */
    public int $partial;

    /** @var non-negative-int */
    public int $failed;

    /** @var non-negative-int */
    public int $skipped;

    /** @var non-negative-int */
    public int $pages;

    /**
     * @throws InvalidArgumentException When a counter is negative
     */
    public function __construct(
        int $ok,
        int $partial,
        int $failed,
        int $skipped,
        int $pages,
    ) {
        $this->ok      = self::counter('ok', $ok);
        $this->partial = self::counter('partial', $partial);
        $this->failed  = self::counter('failed', $failed);
        $this->skipped = self::counter('skipped', $skipped);
        $this->pages   = self::counter('pages', $pages);
    }

    /**
     * Sums the projects into the counters the report publishes.
     *
     * @param list<ProjectResult> $projects
     *
     * @throws InvalidArgumentException When a counter would be negative
     */
    public static function of(array $projects): self
    {
        $ok      = 0;
        $partial = 0;
        $failed  = 0;
        $skipped = 0;
        $pages   = 0;

        foreach ($projects as $project) {
            match ($project->status) {
                ProjectStatus::Ok      => $ok++,
                ProjectStatus::Partial => $partial++,
                ProjectStatus::Failed  => $failed++,
                ProjectStatus::Skipped => $skipped++,
            };

            $pages += $project->renderedPages();
        }

        return new self(
            ok     : $ok,
            partial: $partial,
            failed : $failed,
            skipped: $skipped,
            pages  : $pages,
        );
    }

    /**
     * @return non-negative-int
     *
     * @throws InvalidArgumentException When the counter is negative
     */
    private static function counter(string $name, int $value): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException(
                sprintf('The "%s" total is %d, which is not a count.', $name, $value),
            );
        }

        return $value;
    }

    /**
     * Whether these counters say the same thing as another set, which is how a summary is checked
     * against the projects it claims to summarise.
     */
    public function equals(self $other): bool
    {
        return (
            $this->ok === $other->ok
                && $this->partial === $other->partial
                && $this->failed === $other->failed
                && $this->skipped === $other->skipped
                && $this->pages === $other->pages
        );
    }
}
