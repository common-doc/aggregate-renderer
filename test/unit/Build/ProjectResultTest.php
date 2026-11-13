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

namespace CommonDocTest\Aggregate\Build;

use CommonDoc\Aggregate\Build\PageResult;
use CommonDoc\Aggregate\Build\PageStatus;
use CommonDoc\Aggregate\Build\ProjectResult;
use CommonDoc\Aggregate\Build\ProjectStatus;
use CommonDoc\Aggregate\Build\ReasonCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectResult::class)]
#[CoversMethod(ProjectResult::class, '__construct')]
#[CoversMethod(ProjectResult::class, 'renderedPages')]
final class ProjectResultTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function sitePathsThatAreNotDerived(): array
    {
        return [
            'empty'            => [''],
            'uppercase'        => ['Aggregate-Renderer'],
            'underscore'       => ['aggregate_renderer'],
            'double hyphen'    => ['aggregate--renderer'],
            'leading hyphen'   => ['-aggregate'],
            'trailing hyphen'  => ['aggregate-'],
            'space'            => ['aggregate renderer'],
            'path separator'   => ['common-doc/aggregate-renderer'],
            'trailing newline' => ["aggregate-renderer\n"],
        ];
    }

    #[Test]
    public function countsOnlyTheRenderedPages(): void
    {
        $project = $this->project(
            status     : ProjectStatus::Partial,
            pages      : [
                $this->rendered('https://docs.example.org/aggregate-renderer/1.0/'),
                $this->rendered('https://docs.example.org/aggregate-renderer/1.0/guide/'),
                $this->failed('https://docs.example.org/aggregate-renderer/1.0/guide/setup/'),
            ],
            reasonCodes: [ReasonCode::RenderFailed],
        );

        static::assertSame(2, $project->renderedPages());
    }

    #[Test]
    public function recordsACompleteProject(): void
    {
        $project = $this->project(
            status     : ProjectStatus::Ok,
            pages      : [$this->rendered('https://docs.example.org/aggregate-renderer/1.0/')],
            reasonCodes: [],
        );

        static::assertSame('aggregate-renderer', $project->sitePath);
        static::assertSame(ProjectStatus::Ok, $project->status);
        static::assertSame(1, $project->renderedPages());
        static::assertSame([], $project->reasonCodes);
    }

    #[Test]
    public function recordsAFailedProject(): void
    {
        $project = $this->project(
            status     : ProjectStatus::Failed,
            pages      : [$this->failed('https://docs.example.org/aggregate-renderer/1.0/')],
            reasonCodes: [ReasonCode::RenderFailed],
        );

        static::assertSame(0, $project->renderedPages());
        static::assertCount(1, $project->pages);
    }

    #[Test]
    public function recordsAPartialProject(): void
    {
        $project = $this->project(
            status     : ProjectStatus::Partial,
            pages      : [
                $this->rendered('https://docs.example.org/aggregate-renderer/1.0/'),
                $this->failed('https://docs.example.org/aggregate-renderer/1.0/guide/'),
            ],
            reasonCodes: [ReasonCode::RenderFailed],
        );

        static::assertSame(ProjectStatus::Partial, $project->status);
        static::assertSame(1, $project->renderedPages());
        static::assertSame([ReasonCode::RenderFailed], $project->reasonCodes);
    }

    #[Test]
    public function recordsASkippedProject(): void
    {
        $project = $this->project(
            status     : ProjectStatus::Skipped,
            pages      : [],
            reasonCodes: [ReasonCode::MissingDocDirectory],
        );

        static::assertSame(ProjectStatus::Skipped, $project->status);
        static::assertSame([], $project->pages);
    }

    #[Test]
    public function rejectsACompleteProjectThatCarriesAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reports "ok"');

        $this->project(
            status     : ProjectStatus::Ok,
            pages      : [$this->rendered('https://docs.example.org/aggregate-renderer/1.0/')],
            reasonCodes: [ReasonCode::RenderFailed],
        );
    }

    #[Test]
    public function rejectsADuplicatedReasonCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not ordered and de-duplicated');

        $this->project(
            status     : ProjectStatus::Partial,
            pages      : [
                $this->rendered('https://docs.example.org/aggregate-renderer/1.0/'),
                $this->failed('https://docs.example.org/aggregate-renderer/1.0/guide/'),
            ],
            reasonCodes: [ReasonCode::RenderFailed, ReasonCode::RenderFailed],
        );
    }

    #[Test]
    public function rejectsAFailedProjectThatRenderedAPage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reports "failed"');

        $this->project(
            status     : ProjectStatus::Failed,
            pages      : [$this->rendered('https://docs.example.org/aggregate-renderer/1.0/')],
            reasonCodes: [ReasonCode::RenderFailed],
        );
    }

    #[Test]
    public function rejectsAPartialProjectWithoutAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->project(
            status     : ProjectStatus::Partial,
            pages      : [$this->rendered('https://docs.example.org/aggregate-renderer/1.0/')],
            reasonCodes: [],
        );
    }

    #[Test]
    public function rejectsAPartialProjectWithoutARenderedPage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reports "partial"');

        $this->project(
            status     : ProjectStatus::Partial,
            pages      : [$this->failed('https://docs.example.org/aggregate-renderer/1.0/')],
            reasonCodes: [ReasonCode::RenderFailed],
        );
    }

    #[Test]
    #[DataProvider('sitePathsThatAreNotDerived')]
    public function rejectsASitePathThatIsNotTheDerivedForm(string $sitePath): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProjectResult(
            sitePath   : $sitePath,
            status     : ProjectStatus::Ok,
            pages      : [],
            reasonCodes: [],
        );
    }

    #[Test]
    public function rejectsASkippedProjectThatCarriesAPage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reports "skipped"');

        $this->project(
            status     : ProjectStatus::Skipped,
            pages      : [$this->failed('https://docs.example.org/aggregate-renderer/1.0/')],
            reasonCodes: [ReasonCode::MissingDocDirectory],
        );
    }

    #[Test]
    public function rejectsPagesThatAreNotOrderedByAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not ordered by address');

        $this->project(
            status     : ProjectStatus::Ok,
            pages      : [
                $this->rendered('https://docs.example.org/aggregate-renderer/1.0/guide/'),
                $this->rendered('https://docs.example.org/aggregate-renderer/1.0/'),
            ],
            reasonCodes: [],
        );
    }

    #[Test]
    public function rejectsReasonCodesThatAreNotOrderedByWireValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not ordered and de-duplicated');

        $this->project(
            status     : ProjectStatus::Partial,
            pages      : [
                $this->rendered('https://docs.example.org/aggregate-renderer/1.0/'),
                $this->failed('https://docs.example.org/aggregate-renderer/1.0/guide/'),
            ],
            reasonCodes: [ReasonCode::WriteFailed, ReasonCode::RenderFailed],
        );
    }

    #[Test]
    public function rejectsTwoPagesWithTheSameAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not ordered by address');

        $address = 'https://docs.example.org/aggregate-renderer/1.0/';

        $this->project(
            status     : ProjectStatus::Ok,
            pages      : [$this->rendered($address), $this->rendered($address)],
            reasonCodes: [],
        );
    }

    private function failed(string $address): PageResult
    {
        return new PageResult(
            address   : $address,
            status    : PageStatus::Failed,
            reasonCode: ReasonCode::RenderFailed,
            fromCache : false,
        );
    }

    /**
     * @param list<PageResult> $pages
     * @param list<ReasonCode> $reasonCodes
     */
    private function project(ProjectStatus $status, array $pages, array $reasonCodes): ProjectResult
    {
        return new ProjectResult(
            sitePath   : 'aggregate-renderer',
            status     : $status,
            pages      : $pages,
            reasonCodes: $reasonCodes,
        );
    }

    private function rendered(string $address): PageResult
    {
        return new PageResult(
            address   : $address,
            status    : PageStatus::Rendered,
            reasonCode: null,
            fromCache : false,
        );
    }
}
