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

namespace CommonDoc\Aggregate\Navigation;

use CommonDoc\Aggregate\Build\Corpus;
use CommonDoc\Aggregate\Build\TargetCorpus;
use CommonDoc\Aggregate\Http\Middleware\NoOpMiddleware;
use CommonDoc\Aggregate\Render\PageAddressFactory;
use CommonDoc\Aggregate\Render\PageLabel;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;
use Mezzio\Router\Exception\DuplicateRouteException;
use Mezzio\Router\RouteCollectorInterface;
use Override;
use Webware\Router\RouteProviderInterface;

use function basename;
use function dirname;
use function sort;
use function sprintf;
use function str_replace;
use function substr;

/**
 * Registers one named route per published document.
 *
 * The route name is the document's identity everywhere else in the build, and it is derived from the
 * target and the document path alone. Nothing holds an index from a document to a name or from a name
 * to an address: the router holds the names it was given, and asking it for a name it does not hold is
 * the existence check every caller already performs.
 *
 * A route carries the navigation metadata the sidebar reads, so the label, the nesting and the order
 * of an entry are properties of the one route that publishes the page rather than a second structure
 * that can drift from it.
 */
final readonly class DocumentRouteProvider implements RouteProviderInterface
{
    public function __construct(
        private PageAddressFactory $addresses,
        private Corpus $corpus,
        private string $navId = 'docs',
    ) {}

    /**
     * The route name for a document path, which is also its identifier everywhere else.
     *
     * Pure: no state, so the provider and the link rewriter derive the same name from the same input
     * without either holding an index. The separator is a pipe because a document name may contain a
     * dot, and `api.v2.md` and `api/v2.md` are two documents that must not share a name. An `index`
     * is never collapsed, because `api-reference.md` and `api-reference/index.md` publish to two
     * different addresses.
     *
     * @param non-empty-string $documentPath Document path relative to the project's documentation directory
     *
     * @return non-empty-string
     */
    public static function nameFor(Target $target, string $documentPath): string
    {
        return sprintf(
            'p|%s|%s|%s',
            $target->project->sitePath,
            $target->version,
            str_replace(
                search : '/',
                replace: '|',
                subject: substr(
                    string: $documentPath,
                    offset: 0,
                    length: -3,
                ),
            ),
        );
    }

    /**
     * @throws DuplicateRouteException When two documents publish to one path or one name
     * @throws InvalidArgumentException When a document path cannot be turned into a page address
     */
    #[Override]
    public function registerRoutes(RouteCollectorInterface $routeCollector): void
    {
        foreach ($this->corpus->projects as $project) {
            foreach ($project->targets as $target) {
                $documents = $target->documents;

                sort($documents);

                foreach ($documents as $order => $documentPath) {
                    $routeCollector->get(
                        $this->addresses->forDocument($target->target, $documentPath)->path(),
                        new NoOpMiddleware(),
                        self::nameFor($target->target, $documentPath),
                    )->setOptions([
                        'navigation' => [$this->navId],
                        'label'      => PageLabel::for($target->target, $documentPath),
                        'project'    => $target->target->project->sitePath,
                        'version'    => $target->target->version,
                        'parent'     => $this->parentOf($target, $documentPath),
                        'order'      => $order,
                    ]);
                }
            }
        }
    }

    /**
     * The directory landing page a document hangs from, or null when it hangs from the version root.
     *
     * The name is returned even when that directory has no `index.md`: navigation drops an item whose
     * parent name is absent into the top level, so a missing landing page degrades to a flat entry
     * instead of hiding the document. The lookup is safe because registration finishes before the
     * first page is rendered, and the recursion terminates because the special case is skipped for a
     * document at the version root.
     *
     * @param non-empty-string $documentPath
     */
    private function parentOf(TargetCorpus $target, string $documentPath): ?string
    {
        $directory = dirname($documentPath);

        if ('.' === $directory) {
            return null;
        }

        // An index.md is the landing page of its own directory, so its parent is the directory above.
        if ('index.md' === basename($documentPath)) {
            $directory = dirname($directory);
        }

        return self::nameFor(
            $target->target,
            '.' === $directory ? 'index.md' : "{$directory}/index.md",
        );
    }
}
