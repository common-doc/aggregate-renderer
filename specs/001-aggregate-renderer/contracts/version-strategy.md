# Contract: version resolution seam

**Feature**: `001-aggregate-renderer` | **Plan**: [../plan.md](../plan.md)

The decision seam is pure. It reads its candidates, decides, and returns. It performs no input or
output, starts no process, and touches no version control state, so it is unit-testable with no
fixture and no filesystem.

## Signature

```php
<?php

declare(strict_types=1);

namespace CommonDoc\Aggregate\Version;

interface StrategyInterface
{
    /**
     * @param list<non-empty-string> $candidates
     *
     * @return list<Target>
     */
    public function resolve(array $candidates): array;
}
```

## Input

`$candidates` is a list of the names available for one project:

- For the reference strategy, the reference names reported by the repository, for example `v1.2.0`,
  `1.2.x`, `main`.
- For the tree strategy, the immediate subdirectory names under the project's documentation
  directory, for example `1.2.0`, `1.2`, `drafts`.

Candidates are unordered and may be duplicated. The seam is responsible for its own ordering and
de-duplication so that two callers cannot disagree.

## Output

`list<Target>`, ordered by version compared segment by segment numerically, then as strings. The
returned list is never keyed and never contains a duplicate `Target`.

## Accepted version grammar

A version is `MAJOR.MINOR` or `MAJOR.MINOR.PATCH`, matching `^\d+\.\d+(\.\d+)?$`. Any candidate that
does not project onto this grammar is excluded rather than repaired.

| Candidate form | Version produced | Applies to |
|---|---|---|
| `vX.Y.Z` | `X.Y.Z` | Reference strategy |
| `X.Y.x` | `X.Y` | Reference strategy |
| `X.Y.Z` | `X.Y.Z` | Tree strategy |
| `X.Y` | `X.Y` | Tree strategy |
| anything else | none | Both |

Because the reference forms minus their `v` prefix and their `.x` placeholder are exactly the tree
forms, a corpus whose tags and version directories agree yields identical output from both
implementations. This equality is proven by one parity test that runs both strategies over the same
fixture corpus and asserts the two `list<Target>` values are equal element for element.

## Guarantees

1. Deterministic. The same candidates always produce the same list.
2. Side-effect free. Nothing is written, no process is started, no network call is made.
3. Total. An empty or unusable candidate list returns an empty list rather than failing; the caller
   turns an empty list into the `no-versions` reason code and a `skipped` project.
4. Independent of ordering. Permuting the candidates does not change the output.

## Parity test

The parity test is one test method, in `test/unit/Version/`, that:

1. Builds one project fixture whose corpus carries both reference names and matching version
   directory names.
2. Resolves with the reference strategy and with the tree strategy.
3. Asserts the two results are equal with `static::assertSame`.

No second parity test is added. If a later change makes the two implementations diverge, this test is
the single place that reports it.
