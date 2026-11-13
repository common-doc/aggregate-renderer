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

namespace CommonDocTest\Aggregate\Support;

use CommonDoc\Aggregate\Source\GitFailure;
use CommonDoc\Aggregate\Source\GitRunnerInterface;

use function array_shift;

/**
 * A process seam that answers from a script instead of running git.
 *
 * Tests read the recorded call list to prove which git command was built and which directory it was
 * run in, and queue either an output or a failure per call.
 */
final class GitRunner implements GitRunnerInterface
{
    /** @var list<array{arguments: list<non-empty-string>, workingDirectory: string}> */
    public array $calls = [];

    /** @var list<string|GitFailure> */
    private array $responses = [];

    public function failing(GitFailure $failure): self
    {
        $this->responses[] = $failure;

        return $this;
    }

    public function returning(string $output): self
    {
        $this->responses[] = $output;

        return $this;
    }

    /**
     * @param list<non-empty-string> $arguments
     * @param array<string, string>  $environment
     */
    public function run(array $arguments, string $workingDirectory, array $environment = []): string
    {
        $this->calls[] = ['arguments' => $arguments, 'workingDirectory' => $workingDirectory];

        $response = array_shift($this->responses);

        if ($response instanceof GitFailure) {
            throw $response;
        }

        return $response ?? '';
    }
}
