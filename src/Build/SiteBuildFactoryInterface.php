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

use CommonDoc\Aggregate\Config\SiteConfig;
use InvalidArgumentException;
use League\CommonMark\Exception\AlreadyInitializedException;
use Mezzio\Template\Exception\ExceptionInterface as TemplateExceptionInterface;

/**
 * The build as the command obtains it.
 *
 * A build cannot exist before the configuration does: the output tree, the git source and the
 * renderer all come from it. The command therefore asks for a build once it has read the
 * configuration, which keeps the command sequencing the build rather than assembling it.
 *
 * @internal
 */
interface SiteBuildFactoryInterface
{
    /**
     * @throws AlreadyInitializedException When the markdown environment has already been configured
     * @throws InvalidArgumentException    When the configuration cannot produce a build
     * @throws TemplateExceptionInterface When the template map or the helper manager refuses a name
     */
    public function create(SiteConfig $config): SiteBuild;
}
