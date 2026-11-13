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

namespace CommonDocTest\Aggregate\Config;

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Config\Section;
use CommonDoc\Aggregate\Exception\InvalidSiteConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(Section::class)]
#[CoversMethod(Section::class, 'keys')]
#[CoversMethod(Section::class, 'pathFor')]
#[CoversMethod(Section::class, 'object')]
#[CoversMethod(Section::class, 'optionalObject')]
#[CoversMethod(Section::class, 'string')]
#[CoversMethod(Section::class, 'optionalString')]
#[CoversMethod(Section::class, 'rejectUnknownKeys')]
final class SectionTest extends TestCase
{
    #[Test]
    public function acceptsADeclaredMember(): void
    {
        $this->expectNotToPerformAssertions();

        $this->root()->rejectUnknownKeys(['organization', 'hostname', 'empty', 'count']);
    }

    #[Test]
    public function addressesNestedMembersByTheirFullKeyPath(): void
    {
        static::assertSame(
            'organization.name',
            $this->root()->object('organization')->pathFor('name'),
        );
    }

    #[Test]
    public function addressesRootMembersByTheirBareKey(): void
    {
        static::assertSame('hostname', $this->root()->pathFor('hostname'));
    }

    #[Test]
    public function listsMemberNamesAsStrings(): void
    {
        static::assertSame(['organization', 'hostname', 'empty', 'count'], $this->root()->keys());
    }

    #[Test]
    public function readsAnOptionalObjectAsNullWhenItIsAbsent(): void
    {
        static::assertNull($this->root()->optionalObject('versions'));
    }

    #[Test]
    public function readsAnOptionalObjectWhenItIsPresent(): void
    {
        static::assertNotNull($this->root()->optionalObject('organization'));
    }

    #[Test]
    public function readsAnOptionalStringAsNullWhenItIsAbsent(): void
    {
        static::assertNull($this->root()->optionalString('versions'));
    }

    #[Test]
    public function readsAnOptionalStringWhenItIsPresent(): void
    {
        static::assertSame('docs.example.com', $this->root()->optionalString('hostname'));
    }

    #[Test]
    public function readsARequiredObject(): void
    {
        $organization = $this->root()->object('organization');

        static::assertSame('common-doc', $organization->string('name'));
    }

    #[Test]
    public function readsARequiredString(): void
    {
        static::assertSame('docs.example.com', $this->root()->string('hostname'));
    }

    #[Test]
    public function rejectsAMemberTheSchemaDoesNotDeclare(): void
    {
        $this->assertRejected('hostname', function (): void {
            $this->root()->rejectUnknownKeys(['organization', 'empty', 'count']);
        });
    }

    #[Test]
    public function rejectsAnAbsentRequiredObject(): void
    {
        $this->assertRejected('output', fn(): Section => $this->root()->object('output'));
    }

    #[Test]
    public function rejectsAnAbsentRequiredString(): void
    {
        $this->assertRejected('versions', fn(): string => $this->root()->string('versions'));
    }

    #[Test]
    public function rejectsAnOptionalStringThatIsEmpty(): void
    {
        $this->assertRejected('empty', fn(): ?string => $this->root()->optionalString('empty'));
    }

    #[Test]
    public function rejectsAnUndeclaredMemberBelowTheRootWithItsFullPath(): void
    {
        $this->assertRejected('organization.colour', function (): void {
            $this->root()->object('organization')->rejectUnknownKeys(['name']);
        });
    }

    #[Test]
    public function rejectsARequiredObjectThatIsNotAnObject(): void
    {
        $this->assertRejected('hostname', fn(): Section => $this->root()->object('hostname'));
    }

    #[Test]
    public function rejectsARequiredStringThatIsEmpty(): void
    {
        $this->assertRejected('empty', fn(): string => $this->root()->string('empty'));
    }

    #[Test]
    public function rejectsARequiredStringThatIsNotAString(): void
    {
        $this->assertRejected('count', fn(): string => $this->root()->string('count'));
    }

    /**
     * @param callable(): mixed $action
     */
    private function assertRejected(string $key, callable $action): void
    {
        try {
            $action();
        } catch (InvalidSiteConfigException $exception) {
            static::assertSame($key, $exception->key);
            static::assertSame(ReasonCode::InvalidSiteConfig, $exception->reasonCode);

            return;
        }

        static::fail(sprintf('The section was expected to be rejected at "%s".', $key));
    }

    private function root(): Section
    {
        /** @var array<array-key, mixed> $members */
        $members = [
            'organization' => ['name' => 'common-doc', 'colour' => 'blue'],
            'hostname'     => 'docs.example.com',
            'empty'        => '',
            'count'        => 1,
        ];

        return new Section($members, 'site.json');
    }
}
