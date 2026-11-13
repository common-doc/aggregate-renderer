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

use CommonDoc\Aggregate\Exception\GitFailureException;
use Override;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * The one place this package starts a process.
 *
 * The command is always an argument list, never a shell string, so a repository name or a
 * documentation directory from configuration cannot become a shell token. The environment is a
 * parameter rather than an interpolation for the same reason: a credential handed to git travels
 * beside the argument list, not inside it, so it cannot reach a diagnostic, a log or a report.
 */
final readonly class GitCommand implements GitRunnerInterface
{
    private const float TIMEOUT = 300.0;

    public function __construct(
        private string $binary = 'git',
    ) {}

    /**
     * Runs one git command and returns its standard output unchanged.
     *
     * The bytes are returned as git wrote them because a document's trailing whitespace is its
     * content; a caller that wants a trimmed value trims it.
     *
     * @param list<non-empty-string> $arguments
     * @param string                 $workingDirectory A directory the caller has resolved or created
     * @param array<string, string>  $environment      Added to the environment of this invocation only
     *
     * @throws GitFailureException When git cannot be started or exits with a non-zero code
     */
    #[Override]
    public function run(array $arguments, string $workingDirectory, array $environment = []): string
    {
        try {
            $process = new Process(
                command: [$this->binary, ...$arguments],
                cwd    : $workingDirectory,
                env    : $environment,
                timeout: self::TIMEOUT,
            );

            $process->run();

            if (! $process->isSuccessful()) {
                throw GitFailureException::failed(
                    arguments : $arguments,
                    exitCode  : $process->getExitCode(),
                    diagnostic: $process->getErrorOutput(),
                );
            }

            return $process->getOutput();
        } catch (ExceptionInterface $exception) {
            throw GitFailureException::didNotRun(
                arguments : $arguments,
                diagnostic: $exception->getMessage(),
            );
        }
    }
}
