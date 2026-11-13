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
use CommonDoc\Aggregate\Source\SourceInterface;
use InvalidArgumentException;
use League\CommonMark\Exception\AlreadyInitializedException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;

/**
 * The renderer as the build sees it.
 *
 * A renderer is built per build rather than once per process, because its output root, its hostname
 * and its template map all come from the site configuration, which is only read at the start of a
 * build. The build therefore holds this factory and asks for the renderer once it has the
 * configuration, rather than being handed a renderer it could not have been given sooner.
 *
 * The source is passed in rather than built here, so one checkout cache serves the whole build
 * instead of one per collaborator.
 *
 * @internal
 */
interface SiteRendererFactoryInterface
{
    /**
     * @throws AlreadyInitializedException When the markdown environment has already been configured
     * @throws InvalidArgumentException    When the configuration cannot produce an output tree or a host name,
     *                                     or the output root or host name is refused
     * @throws TemplateExceptionInterface When the template map or the helper manager refuses a name
     */
    public function create(SiteConfig $config, SourceInterface $source): SiteRendererInterface;
}
