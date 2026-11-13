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

use InvalidArgumentException;

use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;

/**
 * One output file and the public address it serves, as an indivisible pair.
 *
 * The two members are never derived independently anywhere in the build: a stage either has both or
 * has neither, so no page is written to a file that no address names, and no address is recorded for
 * a file that nothing writes.
 *
 * The file is relative to the output directory and uses forward slashes on every platform, so the
 * value is safe to record and compare regardless of where the build ran.
 */
final readonly class PageAddress
{
    /**
     * @param string $file    Output file, relative to the output directory, checked below
     * @param string $address Public address that the file serves, checked below
     *
     * @throws InvalidArgumentException When the pair cannot serve one page
     */
    public function __construct(
        public string $file,
        public string $address,
    ) {
        if ('' === $file || str_starts_with($file, '/')) {
            throw new InvalidArgumentException(
                sprintf('The output file "%s" is not relative to the output directory.', $file),
            );
        }

        if (! str_ends_with($file, 'index.html')) {
            throw new InvalidArgumentException(
                sprintf('The output file "%s" does not serve a directory address.', $file),
            );
        }

        if (! str_starts_with($address, 'https://') || ! str_ends_with($address, '/')) {
            throw new InvalidArgumentException(
                sprintf('The page address "%s" is not a directory address on https.', $address),
            );
        }

        if (! str_ends_with($address, substr(
            string: $file,
            offset: 0,
            length: -10,
        ))) {
            throw new InvalidArgumentException(
                sprintf('The page address "%s" does not name the output file "%s".', $address, $file),
            );
        }
    }
}
