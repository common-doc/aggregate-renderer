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

namespace CommonDoc\Aggregate\Output;

use InvalidArgumentException;

use function explode;
use function in_array;
use function preg_match;
use function sprintf;

/**
 * A path inside the published tree.
 *
 * Only one spelling is accepted, so two files cannot be the same file under two names: no absolute
 * path, no empty segment, no backslash, and no `..` segment that would write outside the tree. The
 * grammar is deliberately narrow because it is also the grammar a page address is compared against,
 * and a name that fails here would otherwise reach the filesystem.
 */
final readonly class RelativePath
{
    private const string PATTERN = '~^[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)*$~D';

    /** @var non-empty-string */
    private string $value;

    /**
     * @throws InvalidArgumentException When the value is not a relative path inside the tree
     */
    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'The output path "%s" is not relative to the output directory.',
                $value,
            ));
        }

        if (in_array(
            needle  : '..',
            haystack: explode(
                separator: '/',
                string   : $value,
            ),
            strict  : true,
        )) {
            throw new InvalidArgumentException(sprintf(
                'The output path "%s" escapes the output directory.',
                $value,
            ));
        }

        /** @var non-empty-string $value */
        $this->value = $value;
    }

    /**
     * @return non-empty-string
     */
    public function value(): string
    {
        return $this->value;
    }
}
