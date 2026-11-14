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

/**
 * What the link rewriter did with one href.
 *
 * The set is closed for the same reason the reason codes are: a count a workflow scripts against
 * cannot gain a case without the report contract changing.
 */
enum LinkOutcome: string
{
    /** Rewritten to the published address of the document it names. */
    case Documents = 'documents';

    /** Names a document the router does not hold, so it was left exactly as written. */
    case Unresolved = 'unresolved';

    /** Root absolute, already in published form, and not verified against the router. */
    case Absolute = 'absolute';

    /** A scheme, a mail address or a telephone number: not part of this site. */
    case External = 'external';

    /** A fragment of the page it appears on. */
    case Anchors = 'anchors';

    /** A relative link that does not name a document, such as an image or a plain file. */
    case NonDocument = 'non-document-link';
}
