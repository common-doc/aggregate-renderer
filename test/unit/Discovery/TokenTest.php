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

namespace CommonDocTest\Aggregate\Discovery;

use CommonDoc\Aggregate\Build\ReasonCode;
use CommonDoc\Aggregate\Discovery\Token;
use CommonDoc\Aggregate\Exception\DiscoveryException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function getenv;
use function putenv;

#[CoversClass(Token::class)]
#[CoversMethod(Token::class, '__construct')]
#[CoversMethod(Token::class, 'reveal')]
final class TokenTest extends TestCase
{
    private const string VARIABLE = 'AGGREGATE_RENDERER_TEST_TOKEN';

    private const string OTHER = 'AGGREGATE_RENDERER_TEST_TOKEN_OTHER';

    #[Test]
    public function readsOnlyTheVariableItWasGiven(): void
    {
        putenv(self::OTHER . '=ghp_other');
        putenv(self::VARIABLE . '=ghp_ours');

        static::assertSame('ghp_ours', new Token(self::VARIABLE)->reveal());
        static::assertSame('ghp_other', getenv(self::OTHER));
    }

    #[Test]
    public function readsTheVariableOnEveryCallRatherThanCachingIt(): void
    {
        $token = new Token(self::VARIABLE);

        putenv(self::VARIABLE . '=first');
        static::assertSame('first', $token->reveal());

        putenv(self::VARIABLE . '=second');
        static::assertSame('second', $token->reveal());
    }

    #[Test]
    public function refusesAnUnsetVariable(): void
    {
        $token = new Token(self::VARIABLE);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionMessage(
            'The environment variable "'
                . self::VARIABLE
                . '" is unset or empty, so no organization token is available.',
        );

        $token->reveal();
    }

    #[Test]
    public function refusesAVariableThatHoldsNothing(): void
    {
        putenv(self::VARIABLE . '=');

        $token = new Token(self::VARIABLE);

        $this->expectException(DiscoveryException::class);
        $this->expectExceptionCode(0);

        $token->reveal();
    }

    #[Test]
    public function refusesAVariableThatHoldsOnlyWhitespace(): void
    {
        putenv(self::VARIABLE . '=   ');

        try {
            new Token(self::VARIABLE)->reveal();

            static::fail('A whitespace-only variable must not produce a token.');
        } catch (DiscoveryException $failed) {
            static::assertSame(ReasonCode::MissingToken, $failed->reasonCode);
        }
    }

    #[Test]
    public function revealsTheValueTheConfiguredVariableHolds(): void
    {
        putenv(self::VARIABLE . '=ghp_example');

        static::assertSame('ghp_example', new Token(self::VARIABLE)->reveal());
    }

    #[Test]
    public function trimsTheSurroundingWhitespaceOfTheValue(): void
    {
        putenv(self::VARIABLE . '=  ghp_example  ');

        static::assertSame('ghp_example', new Token(self::VARIABLE)->reveal());
    }

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);
        putenv(self::OTHER);

        parent::tearDown();
    }
}
