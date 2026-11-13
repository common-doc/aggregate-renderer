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

namespace CommonDoc\Aggregate\Source;

/**
 * The process seam every git read passes through.
 *
 * It exists so the source collaborators are testable without running git, and so exactly one class
 * in this package ever constructs a process. The arguments are always a list, never a shell string.
 *
 * @internal
 */
interface GitRunnerInterface
{
    /**
     * Runs one command and returns its standard output unchanged.
     *
     * @param list<non-empty-string> $arguments
     * @param string                 $workingDirectory A directory the caller has resolved or created
     * @param array<string, string>  $environment      Added to the environment of this invocation only
     *
     * @throws GitFailure When the command cannot be run or exits with a non-zero code
     */
    public function run(array $arguments, string $workingDirectory, array $environment = []): string;
}
