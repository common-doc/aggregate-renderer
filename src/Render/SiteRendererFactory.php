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

use CommonDoc\Aggregate\Config\SiteConfig;
use CommonDoc\Aggregate\Output\OutputTree;
use CommonDoc\Aggregate\Source\SourceInterface;
use CommonDoc\View\PageContext;
use InvalidArgumentException;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use Override;

/**
 * Builds the renderer a site configuration describes.
 *
 * The page context, the parser and the renderer are built here together on purpose: they share one
 * CommonMark environment, and the listener on that environment reads the same context instance the
 * target renderer writes to. Splitting that across factories would make the sharing an assumption
 * held at three call sites rather than a fact established in one place.
 */
final readonly class SiteRendererFactory implements SiteRendererFactoryInterface
{
    /**
     * @throws AlreadyInitializedException When the markdown environment has already been configured
     * @throws InvalidArgumentException    When the output root or the host name is refused
     * @throws TemplateExceptionInterface  When the template map or the helper manager refuses a name
     */
    #[Override]
    public function create(SiteConfig $config, SourceInterface $source): SiteRendererInterface
    {
        $views       = new ViewFactory();
        $templates   = $views->create(config: $config);
        $pages       = new PageContext();
        $environment = $views->environment(
            templates: $templates,
            pages    : $pages,
        );
        $tree      = new OutputTree(root: $config->outputDirectory);
        $addresses = new PageAddressFactory(hostname: $config->hostname);

        return new SiteRenderer(
            targets : new TargetRenderer(
                tree     : $tree,
                addresses: $addresses,
                source   : $source,
                renderer : new DocumentRenderer(
                    reader: new DocumentReader(parser: new MarkdownParser(environment: $environment)),
                    html  : new HtmlRenderer(environment: $environment),
                ),
                pages    : $pages,
            ),
            index   : new SiteIndex(
                tree     : $tree,
                addresses: $addresses,
                templates: $templates,
            ),
            notFound: new NotFoundPage(
                tree     : $tree,
                templates: $templates,
            ),
        );
    }
}
