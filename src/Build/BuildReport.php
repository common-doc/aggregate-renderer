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

use function array_map;
use function sort;
use function sprintf;

use const SORT_STRING;

/**
 * The machine-readable outcome of one run.
 *
 * The report refuses to exist in a state a workflow would have to second-guess: the exit code is one
 * of the three the contract enumerates, the projects are ordered by site path so two runs over an
 * unchanged corpus serialise byte-identically, and the totals are recomputed from the projects rather
 * than trusted, so a summary can never contradict its own detail.
 */
final readonly class BuildReport
{
    /** @var list<ProjectResult> */
    public array $projects;

    /**
     * @param list<ProjectResult> $projects Ordered by site path
     *
     * @throws InvalidArgumentException When the exit code, the projects or the totals disagree
     */
    public function __construct(
        public int $exitCode,
        array $projects,
        public Total $totals,
    ) {
        $this->projects = self::orderedProjects($projects);

        $this->requireExitCodeToMatchTheProjects();
        $this->requireTotalsToMatchTheProjects();
    }

    /**
     * @param list<ProjectResult> $projects
     *
     * @return list<ProjectResult>
     *
     * @throws InvalidArgumentException When the projects are not a list ordered by site path
     */
    private static function orderedProjects(array $projects): array
    {
        $sitePaths = array_map(
            static fn(ProjectResult $project): string => $project->sitePath,
            $projects,
        );

        $sorted = $sitePaths;
        sort($sorted, SORT_STRING);

        if ($sorted !== $sitePaths) {
            throw new InvalidArgumentException(
                'The report projects are not a list ordered by site path.',
            );
        }

        return $projects;
    }

    /**
     * @throws InvalidArgumentException When the exit code is outside the contract or contradicts the projects
     */
    private function requireExitCodeToMatchTheProjects(): void
    {
        if ($this->exitCode < 0 || $this->exitCode > 2) {
            throw new InvalidArgumentException(sprintf(
                'The exit code %d is not one of 0, 1 or 2.',
                $this->exitCode,
            ));
        }

        $defective = $this->totals->partial + $this->totals->failed;

        if (0 === $this->exitCode && 0 !== $defective) {
            throw new InvalidArgumentException(sprintf(
                'The exit code is 0 while %d projects are partial or failed.',
                $defective,
            ));
        }

        if (1 === $this->exitCode && 0 === $defective) {
            throw new InvalidArgumentException(
                'The exit code is 1 while no project is partial or failed.',
            );
        }

        if (2 === $this->exitCode && 0 !== $this->totals->pages) {
            throw new InvalidArgumentException(sprintf(
                'The exit code is 2 while %d pages were written before rendering was blocked.',
                $this->totals->pages,
            ));
        }
    }

    /**
     * @throws InvalidArgumentException When a counter disagrees with the projects it summarises
     */
    private function requireTotalsToMatchTheProjects(): void
    {
        $counted = Total::of($this->projects);

        if (! $this->totals->equals($counted)) {
            throw new InvalidArgumentException(sprintf(
                'The totals %d/%d/%d/%d with %d pages do not summarise %d/%d/%d/%d with %d pages.',
                $this->totals->ok,
                $this->totals->partial,
                $this->totals->failed,
                $this->totals->skipped,
                $this->totals->pages,
                $counted->ok,
                $counted->partial,
                $counted->failed,
                $counted->skipped,
                $counted->pages,
            ));
        }
    }
}
