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
use CommonDoc\View\DocumentViewRenderer;
use CommonDoc\View\PageContext;
use CommonDoc\View\PageContextListener;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\View\Resolver\TemplateMapResolver;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Event\DocumentPreRenderEvent;
use League\CommonMark\Exception\AlreadyInitializedException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Node\Block\Document;
use Mezzio\LaminasView\LaminasViewRenderer;
use Mezzio\LaminasView\LayoutHelper;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;
use Mezzio\Template\TemplateRendererInterface;

use function array_replace;
use function dirname;

/**
 * Builds the view stack one site renders through.
 *
 * The resolved template map is the whole of the template surface: this package ships a default for
 * every address it renders, so a name absent from a site's `templates.map` falls through to the
 * shared default rather than to an error. The document address is the shared one, `commondoc::document`,
 * because that is the address commondoc's document renderer asks for: this package replaces that entry
 * with its own body template, which emits the body region only and leaves the shell to the layout.
 *
 * The layout is this package's, not commondoc's. The shell owns the doctype, the head, the site chrome
 * and the title, so the document body never has to know it is inside a page.
 */
final readonly class ViewFactory
{
    /** The shared document address this package replaces with its own body template. */
    public const string DOCUMENT = 'commondoc::document';

    /** The aggregate layout shell, which owns the doctype, the head and the site chrome. */
    public const string LAYOUT = 'aggregate::layout';

    public const string SITE_INDEX = 'aggregate::site-index';

    public const string NOT_FOUND = 'aggregate::not-found';

    /**
     * The template map this package ships, before a site's own entries are applied.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public static function defaults(): array
    {
        $directory =
            dirname(
                path  : __DIR__,
                levels: 2,
            ) . '/templates/aggregate';

        return [
            self::DOCUMENT   => "{$directory}/document.phtml",
            self::LAYOUT     => "{$directory}/layout.phtml",
            self::SITE_INDEX => "{$directory}/site-index.phtml",
            self::NOT_FOUND  => "{$directory}/not-found.phtml",
        ];
    }

    private static function helpers(): HelperPluginManager
    {
        return new HelperPluginManager(
            creationContext: new ServiceManager(),
            config         : [
                'factories' => [
                    LayoutHelper::class => InvokableFactory::class,
                ],
            ],
        );
    }

    /**
     * The renderer the build renders through: the same template map, wrapped so the layout is applied.
     *
     * @throws TemplateExceptionInterface When the template map or the helper manager refuses a name
     */
    public function create(SiteConfig $config): TemplateRendererInterface
    {
        $helpers = self::helpers();

        return new LaminasViewRenderer(
            renderer: new PhpRenderer(
                pluginManager   : $helpers,
                templateResolver: $this->resolver(config: $config),
            ),
            helpers : $helpers,
            layout  : self::LAYOUT,
        );
    }

    /**
     * The view pipeline the markdown seam renders into.
     *
     * The document renderer is registered for the document node itself, so a rendered tree arrives at
     * the template as a body rather than as finished HTML, and the view renderer owns the layout wrap.
     * The page listener is registered on the same pipeline with the caller's context instance, so the
     * page a caller sets is the page the document carries and the template reads.
     *
     * @throws AlreadyInitializedException When the environment has already been configured
     */
    public function environment(TemplateRendererInterface $templates, PageContext $pages): EnvironmentInterface
    {
        $environment = new Environment();

        $environment->addExtension(extension: new CommonMarkCoreExtension());
        $environment->addRenderer(
            nodeClass: Document::class,
            renderer : new DocumentViewRenderer(renderer: $templates),
            priority : 10,
        );
        $environment->addEventListener(
            eventClass: DocumentPreRenderEvent::class,
            listener  : new PageContextListener(context: $pages),
        );

        return $environment;
    }

    /**
     * The resolved template map: the shipped defaults, with a site's own entries replacing them.
     *
     * A template address is a name, so an entry a site declared under an empty name is not an address
     * and is dropped rather than resolved: the resolver refuses an empty name, and silently rendering
     * the default for it would hide a mistake in the site's own map.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    public function map(SiteConfig $config): array
    {
        $resolved = [];

        foreach (array_replace(self::defaults(), $config->templateMap) as $name => $template) {
            if ('' === $name) {
                continue;
            }

            $resolved[$name] = $template;
        }

        return $resolved;
    }

    public function renderer(SiteConfig $config): PhpRenderer
    {
        return new PhpRenderer(
            pluginManager   : self::helpers(),
            templateResolver: $this->resolver(config: $config),
        );
    }

    private function resolver(SiteConfig $config): TemplateMapResolver
    {
        return new TemplateMapResolver(map: $this->map(config: $config));
    }
}
