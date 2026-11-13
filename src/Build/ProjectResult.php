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

use function count;
use function preg_match;
use function sprintf;
use function strcmp;

/**
 * What happened to one project.
 *
 * Both lists are ordered here, not by whatever produced them, because the report is byte-compared
 * between runs and an unordered list would make an unchanged corpus produce a changed report. Pages
 * are ordered by address and reason codes by their wire value, which is the order the report schema
 * requires.
 *
 * The status invariants are enforced rather than assumed, so a project cannot claim to be complete
 * while carrying a failed page, and cannot be skipped while carrying a rendered one.
 */
final readonly class ProjectResult
{
    private const string SITE_PATH_PATTERN = '~^[a-z0-9]+(?:-[a-z0-9]+)*$~D';

    /** @var non-empty-string */
    public string $sitePath;

    /** @var list<PageResult> */
    public array $pages;

    /** @var list<ReasonCode> */
    public array $reasonCodes;

    /**
     * @param non-empty-string $sitePath
     * @param list<PageResult> $pages       Ordered by address
     * @param list<ReasonCode> $reasonCodes Ordered by wire value, de-duplicated
     *
     * @throws InvalidArgumentException When the status, the pages and the reason codes disagree
     */
    public function __construct(
        string $sitePath,
        public ProjectStatus $status,
        array $pages,
        array $reasonCodes,
    ) {
        if (preg_match(self::SITE_PATH_PATTERN, $sitePath) !== 1) {
            throw new InvalidArgumentException(
                sprintf('The site path "%s" is not in the derived form.', $sitePath),
            );
        }

        $this->sitePath    = $sitePath;
        $this->pages       = self::orderedPages($sitePath, $pages);
        $this->reasonCodes = self::orderedReasonCodes($sitePath, $reasonCodes);

        $this->requireStatusToAgreeWithItsLists();
    }

    /**
     * @param list<PageResult> $pages
     *
     * @return list<PageResult>
     *
     * @throws InvalidArgumentException When the list is not ordered by address
     */
    private static function orderedPages(string $sitePath, array $pages): array
    {
        $previous = null;

        foreach ($pages as $page) {
            if ($previous instanceof PageResult && strcmp($previous->address, $page->address) >= 0) {
                throw new InvalidArgumentException(sprintf(
                    'The pages of "%s" are not ordered by address: "%s" follows "%s".',
                    $sitePath,
                    $page->address,
                    $previous->address,
                ));
            }

            $previous = $page;
        }

        return $pages;
    }

    /**
     * @param list<ReasonCode> $reasonCodes
     *
     * @return list<ReasonCode>
     *
     * @throws InvalidArgumentException When the list is not ordered by wire value and de-duplicated
     */
    private static function orderedReasonCodes(string $sitePath, array $reasonCodes): array
    {
        $previous = null;

        foreach ($reasonCodes as $reasonCode) {
            if ($previous instanceof ReasonCode && strcmp($previous->value, $reasonCode->value) >= 0) {
                throw new InvalidArgumentException(sprintf(
                    'The reason codes of "%s" are not ordered and de-duplicated: "%s" follows "%s".',
                    $sitePath,
                    $reasonCode->value,
                    $previous->value,
                ));
            }

            $previous = $reasonCode;
        }

        return $reasonCodes;
    }

    /**
     * Pages written for this project, which is zero for a failed or a skipped project.
     */
    public function renderedPages(): int
    {
        $rendered = 0;

        foreach ($this->pages as $page) {
            $rendered += PageStatus::Rendered === $page->status ? 1 : 0;
        }

        return $rendered;
    }

    /**
     * @throws InvalidArgumentException When the status contradicts the pages or the reason codes
     */
    private function requireStatusToAgreeWithItsLists(): void
    {
        $rendered  = $this->renderedPages();
        $hasReason = [] !== $this->reasonCodes;

        $holds = match ($this->status) {
            ProjectStatus::Ok      => ! $hasReason,
            ProjectStatus::Partial => $rendered > 0 && $hasReason,
            ProjectStatus::Failed  => 0 === $rendered && $hasReason,
            ProjectStatus::Skipped => [] === $this->pages && $hasReason,
        };

        if (! $holds) {
            throw new InvalidArgumentException(sprintf(
                'The project "%s" reports "%s" while %d of its %d pages rendered and it carries %d reason codes.',
                $this->sitePath,
                $this->status->value,
                $rendered,
                count($this->pages),
                count($this->reasonCodes),
            ));
        }
    }
}
