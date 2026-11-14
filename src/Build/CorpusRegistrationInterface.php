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

use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Version\Strategy;
use InvalidArgumentException;

/**
 * The work that has to finish before the first page is written.
 *
 * Two phases run here and nowhere else: the corpus is resolved in full, then one route is registered
 * for every page it holds. They are one collaborator because neither is useful alone: a corpus no
 * route names cannot have its links rewritten or its sidebar rendered, and a route table built from a
 * partial corpus would silently miss every document a later project contributes.
 *
 * Nothing is written during either phase, so a corpus that cannot be resolved leaves the output tree
 * untouched.
 *
 * @internal
 */
interface CorpusRegistrationInterface
{
    /**
     * @param list<Project> $projects Ordered by site path
     *
     * @throws InvalidArgumentException When a strategy refuses a project it was given
     */
    public function resolve(array $projects, Strategy $strategy): Corpus;
}
