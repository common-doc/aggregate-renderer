<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Build;

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;

use function array_filter;
use function array_values;
use function count;
use function ksort;
use function strcmp;
use function usort;

/**
 * One project's contribution to the build.
 *
 * The targets travel beside the result rather than inside it, because the result is what the report
 * publishes and the targets are what the site index links. Only the targets whose documents were
 * materialized appear here: a version that never rendered has no pages to link to.
 *
 * The status is derived from what was rendered rather than passed in, so a project cannot claim to be
 * complete while carrying a failed page.
 *
 * @internal
 */
final readonly class ProjectOutcome
{
    /**
     * @param list<Target> $targets Ordered by site path, then version
     */
    public function __construct(
        public ProjectResult $result,
        public array $targets,
    ) {}

    /**
     * A project that produced nothing at all.
     *
     * @throws InvalidArgumentException When the result cannot be constructed
     */
    public static function failed(Project $project, ReasonCode $reason): self
    {
        return new self(new ProjectResult($project->sitePath, ProjectStatus::Failed, [], [$reason]), []);
    }

    /**
     * @param list<Target>     $targets The targets whose documents were materialized, ordered by version
     * @param list<PageResult> $pages
     * @param list<ReasonCode> $reasons The reasons collected while materializing, unordered
     *
     * @throws InvalidArgumentException When the result cannot describe the pages it was given
     */
    public static function for(Project $project, array $targets, array $pages, array $reasons): self
    {
        usort($pages, static fn(PageResult $left, PageResult $right): int => strcmp(
            $left->address,
            $right->address,
        ));

        $rendered = count(array_filter(
            $pages,
            static fn(PageResult $page): bool => PageStatus::Rendered === $page->status,
        ));

        if ($rendered < count($pages)) {
            $reasons[] = ReasonCode::RenderFailed;
        }

        $reasons = self::ordered($reasons);

        return new self(
            new ProjectResult(
                $project->sitePath,
                self::statusOf($rendered, $reasons),
                $pages,
                $reasons,
            ),
            $targets,
        );
    }

    /**
     * @param list<ReasonCode> $reasons
     *
     * @return list<ReasonCode> Ordered by wire value, without duplicates
     */
    private static function ordered(array $reasons): array
    {
        $byValue = [];

        foreach ($reasons as $reason) {
            $byValue[$reason->value] = $reason;
        }

        ksort($byValue);

        return array_values($byValue);
    }

    /**
     * @param list<ReasonCode> $reasons Ordered, and non-empty when the project is not complete
     */
    private static function statusOf(int $rendered, array $reasons): ProjectStatus
    {
        return match (true) {
            [] === $reasons => ProjectStatus::Ok,
            0 === $rendered => ProjectStatus::Failed,
            default => ProjectStatus::Partial,
        };
    }
}
