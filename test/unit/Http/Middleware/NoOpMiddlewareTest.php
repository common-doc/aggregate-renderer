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

namespace CommonDocTest\Aggregate\Http\Middleware;

use CommonDoc\Aggregate\Http\Middleware\NoOpMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(NoOpMiddleware::class)]
#[CoversMethod(NoOpMiddleware::class, 'process')]
final class NoOpMiddlewareTest extends TestCase
{
    #[Test]
    public function handsTheRequestToTheHandlerUnchanged(): void
    {
        $request  = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        static::assertSame($response, new NoOpMiddleware()->process($request, $handler));
    }
}
