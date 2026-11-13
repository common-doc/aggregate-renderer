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

namespace CommonDoc\Aggregate\Render;

use CommonDoc\Aggregate\Output\OutputTree;
use CommonDoc\Aggregate\Version\Target;
use InvalidArgumentException;
use Mezzio\Template\Exception\ExceptionInterface;
use Mezzio\Template\TemplateRendererInterface;
use RuntimeException;

use function array_values;

/**
 * The site index: every published version and the address it is served from.
 *
 * The addresses come from the same derivation the pages were written through rather than from the
 * tree, so the index cannot advertise a directory that no page was published into, and the index
 * lists versions in the order the build already ordered them.
 */
final readonly class SiteIndex
{
    private const string FILE = 'index.html';

    private const string ROOT_DOCUMENT = 'index.md';

    public function __construct(
        private OutputTree $tree,
        private PageAddressFactory $addresses,
        private TemplateRendererInterface $templates,
    ) {}

    /**
     * @param list<Target> $targets Ordered by site path, then by version
     *
     * @return list<array{name: string, versions: list<array{version: string, address: string}>}>
     *
     * @throws InvalidArgumentException When a target cannot name a page
     */
    public function entries(array $targets): array
    {
        $entries = [];

        foreach ($targets as $target) {
            $sitePath = $target->project->sitePath;

            $entries[$sitePath]               ??= ['name' => $target->project->name, 'versions' => []];
            $entries[$sitePath]['versions'][] = [
                'version' => $target->version,
                'address' => $this->addresses
                    ->forDocument(
                        target      : $target,
                        documentPath: self::ROOT_DOCUMENT,
                    )
                    ->address,
            ];
        }

        return array_values($entries);
    }

    /**
     * @param list<Target> $targets Ordered by site path, then by version
     *
     * @throws ExceptionInterface       When the index cannot be rendered
     * @throws InvalidArgumentException When the index file is not relative to the output directory
     * @throws RuntimeException         When the index cannot be written
     */
    public function write(array $targets): void
    {
        $this->tree->write(
            relativePath: self::FILE,
            contents: $this->templates->render(
                name: ViewFactory::SITE_INDEX,
                params: ['entries' => $this->entries($targets)],
            ),
        );
    }
}
