# Phase 1 Data Model: Aggregate Documentation Site Rendering

**Feature**: `001-aggregate-renderer` | **Date**: 2026-11-12 | **Plan**: [plan.md](./plan.md)

All value objects are `readonly` with promoted constructor properties, live under
`CommonDoc\Aggregate\`, and expose no magic accessor. Closed sets are backed enums. No field holds a
`mixed` value. Collections are `list<T>` or `array<string, T>` and are always ordered before they are
compared, hashed or written.

## Project

One onboarded repository, produced by discovery and consumed read-only by everything downstream.

| Field | Type | Notes |
|-------|------|-------|
| `name` | `non-empty-string` | Repository name as the boundary reports it |
| `owner` | `non-empty-string` | Organization or user owning the repository |
| `sitePath` | `non-empty-string` | Derived from `name` per D-02; the report's `slug` field carries this value |
| `docDirectory` | `non-empty-string` | Repository-relative path taken from the `doc-target` property |

Invariants:

- `name` and `docDirectory` are non-empty. A repository whose property value is empty is not
  onboarded and produces no `Project`.
- `sitePath` matches `[a-z0-9]+(-[a-z0-9]+)*`.
- Two `Project` values in one corpus never share `sitePath`. This is checked before rendering and its
  violation is a build error, not an exception escaping the command.
- `owner` participates in the source location, never in any public address.

## Target

One project at one version. It is the only value from which an output file or a public address is
derived, which is what makes "one page, one file, one address" structural rather than conventional.

| Field | Type | Notes |
|-------|------|-------|
| `project` | `Project` | The project being rendered |
| `version` | `non-empty-string` | `MAJOR.MINOR` or `MAJOR.MINOR.PATCH` per D-03 |

Invariants:

- `version` matches `^\d+\.\d+(\.\d+)?$`.
- For a given corpus, `list<Target>` from the reference strategy and from the tree strategy compare
  equal, element for element, in the same order. Ordering is by `sitePath` then by version compared
  segment by segment numerically, so `1.10.0` sorts after `1.9.0`.
- A `Target` carries no filesystem path and no address. Those are derived, never stored, so a stored
  copy cannot go stale.

## Page

One rendered document inside one `Target`.

| Field | Type | Notes |
|-------|------|-------|
| `target` | `Target` | Owning target |
| `sourcePath` | `non-empty-string` | Repository-relative path of the source document |
| `documentPath` | `non-empty-string` | Source path relative to the project's documentation directory, with the markdown suffix removed; `index` for the version root |
| `outputFile` | `non-empty-string` | Derived per D-06 |
| `address` | `non-empty-string` | Derived per D-06 |

Invariants:

- `outputFile` and `address` are produced by one derivation from the same `Target` and
  `documentPath`. There is no code path that produces one without the other.
- Two pages in one build never share `outputFile` or `address`. A collision is a pre-render failure
  with reason code `duplicate-page-address`.
- `address` is absolute, uses the configured hostname, ends with `/`, and contains no version
  control, commit reference, token, or filesystem path.

## SiteConfig

The decoded contents of the site configuration file.

| Field | Type | Source key | Notes |
|-------|------|-----------|-------|
| `organization` | `non-empty-string` | `organization.name` | Organization whose repositories are discovered |
| `tokenEnvVar` | `non-empty-string` | `organization.tokenEnvVar` | Name of the environment variable holding the token; the token itself is never held here |
| `outputDirectory` | `non-empty-string` | `output.directory` | Absolute, or resolved against the configuration file's directory |
| `reportPath` | `non-empty-string` | `output.reportPath` | Absolute, or resolved against the configuration file's directory |
| `cacheDirectory` | `non-empty-string` | `output.cacheDirectory` | Default `.cache/multi-repo` relative to the configuration file's directory |
| `hostname` | `non-empty-string` | `site.hostname` | Bare host, no scheme and no trailing slash |
| `strategy` | `Strategy` | `versions.strategy` | Enum `Ref`, `Tree`; default `Ref` |
| `templateMap` | `array<string, non-empty-string>` | `templates.map` | Template name to file path; default empty |

Invariants:

- All required keys are present and non-empty; the default key is supplied by the reader, not by a
  consumer.
- `hostname` contains no scheme, no slash, and no whitespace.
- `templateMap` keys are non-empty; values resolve to a readable file or the configuration is
  invalid.
- `SiteConfig` contains no secret. The token value is never a field of this value object.

## Configuration hash

| Field | Type | Notes |
|-------|------|-------|
| `hash` | `non-empty-string` | Hash of a canonical encoding of `SiteConfig` |

Invariant: the canonical encoding is key-ordered, excludes `cacheDirectory`, and excludes nothing
else. Two configurations that differ in any other field produce different hashes.

## PageResult

| Field | Type | Notes |
|-------|------|-------|
| `address` | `non-empty-string` | Public address of the page |
| `status` | `PageStatus` | `Rendered` or `Failed` |
| `reasonCode` | `ReasonCode|null` | Present exactly when `status` is `Failed`; `null` listed last |
| `fromCache` | `bool` | Whether the page was reused rather than rendered |

Invariant: `status` and `reasonCode` are consistent; a rendered page has no reason code and a failed
page always has one.

## ProjectResult

| Field | Type | Notes |
|-------|------|-------|
| `sitePath` | `non-empty-string` | The report's `slug` field carries this value |
| `status` | `ProjectStatus` | `Ok`, `Partial`, `Failed`, `Skipped` |
| `pages` | `list<PageResult>` | Ordered by address |
| `reasonCodes` | `list<ReasonCode>` | Ordered, de-duplicated |

Invariants:

- `Ok` has no reason codes.
- `Partial` has at least one rendered page and at least one reason code.
- `Failed` has no rendered page and at least one reason code.
- `Skipped` has no pages and at least one reason code explaining why nothing was attempted.

## BuildReport

| Field | Type | Notes |
|-------|------|-------|
| `exitCode` | `int` | `0`, `1` or `2` per D-08 |
| `totals` | `Total` | Project counts by status plus rendered page count |
| `projects` | `list<ProjectResult>` | Ordered by site path |

Invariants:

- `totals.ok`, `totals.partial`, `totals.failed` and `totals.skipped` sum to the number of discovered
  projects.
- `totals.pages` equals the sum of rendered pages across all projects.
- `exitCode` is `0` only when every project is `Ok`, and `2` only when rendering never started.
- The serialized form matches [contracts/build-report.schema.json](./contracts/build-report.schema.json)
  including the settled field names `exitCode`, `totals.ok`, `totals.partial`, `totals.failed`,
  `totals.skipped`, `totals.pages`, `projects[].slug`, `projects[].status`,
  `projects[].errors[].reasonCode`.

## Total

| Field | Type | Notes |
|-------|------|-------|
| `ok` | `non-negative-int` | Projects rendered completely |
| `partial` | `non-negative-int` | Projects rendered with at least one failed page |
| `failed` | `non-negative-int` | Projects that produced nothing |
| `skipped` | `non-negative-int` | Projects that were not attempted |
| `pages` | `non-negative-int` | Pages rendered across the corpus |

## CacheKey

| Field | Type | Notes |
|-------|------|-------|
| `sourceHash` | `non-empty-string` | Hash of the page's source content |
| `templateHash` | `non-empty-string` | Hash of the applied template set |
| `configHash` | `non-empty-string` | The configuration hash above |

Invariant: the stored key is the hash of the three components in the order listed, and an entry is
reusable only when all three match. A change to any component produces a different key; nothing else
participates, so two runs over an unchanged corpus and unchanged configuration resolve to the same key
no matter what else changed on the machine.

## ReasonCode

Closed enum. The wire form is the lowercased hyphenated name, which is the value written to
`projects[].errors[].reasonCode`. Each value is public vocabulary and appears in
[contracts/build-report.schema.json](./contracts/build-report.schema.json).

| Value | Stage | Meaning |
|-------|-------|---------|
| `invalid-site-config` | Configuration | The configuration file is missing a required key or a value is malformed |
| `missing-token` | Discovery | The named environment variable is unset or empty |
| `rejected-token` | Discovery | The boundary refused the credential |
| `discovery-unavailable` | Discovery | The boundary could not be reached or answered with an unexpected status |
| `duplicate-slug` | Pre-render check | Two projects derived the same site path |
| `duplicate-version` | Resolution | One project resolved the same version twice |
| `duplicate-page-address` | Pre-render check | Two pages derived the same output file or address |
| `no-versions` | Resolution | A project declared no version and publishes nothing |
| `missing-doc-directory` | Source | The declared documentation directory does not exist at the resolved ref |
| `source-unavailable` | Source | The project's sources could not be materialized |
| `render-failed` | Render | A document could not be parsed or rendered |
| `write-failed` | Output | A page or asset could not be written |
| `missing-cname` | Pre-publish check | The site address file is absent from the output tree |

## Enum: ProjectStatus

`Ok`, `Partial`, `Failed`, `Skipped`. Wire form: `ok`, `partial`, `failed`, `skipped`. The first four
are the settled report vocabulary; `ProjectStatus` maps onto it one to one.

## Enum: PageStatus

`Rendered`, `Failed`. Wire form: `rendered`, `failed`.

## Enum: Strategy

`Ref`, `Tree`. Wire form: `ref`, `tree`.

## Status transitions

A project moves through the following states in one build. The states are computed, never persisted,
and the report is written once at the end from the final state of every project.

```text
Discovered
  -> Skipped            resolution produced no version
  -> Ok                 every page rendered
  -> Partial            at least one page rendered, at least one failed
  -> Failed             no page rendered
```

A build moves through the following stages in order. A failure at a stage stops later stages, and the
report records the projects that were never reached as `Skipped` with the blocking reason code.

```text
Configure -> Discover -> Resolve -> Check corpus -> Materialize -> Render -> Write index and not-found
  -> Write site address file -> Verify site address file -> Write report
```

## Relationships

```text
SiteConfig 1 --- * Project
Project    1 --- * Target
Target     1 --- * Page
Page       1 --- 1 CacheKey
Target     1 --- 1 PageAddress (per document)
BuildReport 1 --- * ProjectResult 1 --- * PageResult
```
