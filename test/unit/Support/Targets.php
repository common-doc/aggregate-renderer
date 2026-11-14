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

use CommonDoc\Aggregate\Build\Corpus;
use CommonDoc\Aggregate\Build\ProjectCorpus;
use CommonDoc\Aggregate\Build\TargetCorpus;
use CommonDoc\Aggregate\Discovery\Project;
use CommonDoc\Aggregate\Navigation\DocumentRouteProvider;
use CommonDoc\Aggregate\Navigation\RouteTable;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Version\Target;

/**
 * The corpus and the route table the navigation and link tests build on, so each test states the
 * documents it is about rather than the plumbing that registers them.
 */
final class Targets
{
    public const string HOSTNAME = 'docs.example.org';

    public const string SITE_PATH = 'aggregate-renderer';

    public const string VERSION = '2.0';

    /**
     * @param list<non-empty-string> $documents
     */
    public static function corpus(array $documents, ?Target $target = null): Corpus
    {
        $target ??= self::target();

        return new Corpus(projects: [
            new ProjectCorpus(
                project: $target->project,
                targets: [new TargetCorpus(
                    target   : $target,
                    documents: $documents,
                )],
                reasons: [],
            ),
        ]);
    }

    /**
     * The routes a corpus publishes, in the router every href is generated from.
     *
     * @param list<non-empty-string> $documents
     */
    public static function routeTable(array $documents, ?Target $target = null): RouteTable
    {
        $table = RouteTable::create();

        new DocumentRouteProvider(
            addresses: new PageAddressFactory(hostname: self::HOSTNAME),
            corpus   : self::corpus(
                documents: $documents,
                target   : $target,
            ),
        )->registerRoutes($table->collector);

        return $table;
    }

    public static function target(
        string $sitePath = self::SITE_PATH,
        string $version = self::VERSION,
        string $name = 'aggregate-renderer',
    ): Target {
        return new Target(
            project: new Project(
                name        : $name,
                owner       : 'common-doc',
                sitePath    : $sitePath,
                docDirectory: 'docs',
            ),
            version: $version,
        );
    }
}
