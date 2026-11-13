# Phase 0 Research: Aggregate Documentation Site Rendering

**Feature**: `001-aggregate-renderer` | **Date**: 2026-11-12 | **Plan**: [plan.md](./plan.md)

All decisions that the settled requirements did not fix are recorded here. Each decision states what
was chosen, why, and what else was considered. Nothing below reopens a settled requirement.

## D-01: Meaning of the `doc-target` custom property

**Decision**: The `doc-target` repository custom property carries a repository-relative directory
path, for example `docs`. A non-empty value means the repository is onboarded and names the directory
holding that project's documentation sources. An absent property, an empty value, or a value that is
not a directory path marks the repository as not onboarded.

**Rationale**: Onboarding has to be one change with no per-project workflow edit, and it also has to
locate the sources. Carrying the path in the property keeps `site.json` free of per-project entries,
which is what preserves the one-property contract. Both version strategies need a base directory, so
the property is the natural place for it.

**Alternatives considered**: A boolean flag plus a global `docs` convention (rejected: forces one
layout on every project and gives the tree strategy nothing to configure). A per-project block in
`site.json` (rejected: ends the one-property onboarding contract and reintroduces per-project
configuration drift).

## D-02: Site path derivation and collision handling

**Decision**: A project's site path is its repository name, lowercased, with every run of characters
outside `a-z0-9` collapsed to a single hyphen and leading or trailing hyphens removed. The site path
is not configurable per project. Two projects deriving the same site path is a pre-render failure with
reason code `duplicate-slug`.

**Rationale**: The site path is part of every public address, so it must be stable and predictable
from the repository identity alone. Deriving it from the repository name means a rename is the only
thing that changes it, and a rename is already a deliberate act. Comparing after derivation catches
the two-organizations-one-repository-name case that a repository name comparison would miss.

**Alternatives considered**: Using `owner/repository` in the path (rejected: an ownership transfer
would rewrite every address, and the organization already scopes the corpus). A configurable slug
(rejected: another per-project knob, and a second collision surface).

## D-03: Version naming and the strategy parity contract

**Decision**: A version is a string of the form `MAJOR.MINOR` or `MAJOR.MINOR.PATCH`. The reference
strategy maps a release reference `vX.Y.Z` to `X.Y.Z` and a maintenance branch reference `X.Y.x` to
`X.Y`, and excludes every other reference name. The tree strategy maps each immediate subdirectory
name of the project's documentation directory to a version after validating it has one of those two
forms, and excludes every other directory name. Reference names that map to versions, stripped of
their `v` prefix and their `.x` placeholder, are exactly the strings the tree strategy accepts, so a
corpus whose tags and version directories agree produces identical `list<Target>` from both.

**Rationale**: The parity requirement only has meaning if both strategies agree on what a version
string is. Fixing the accepted grammar in one place and expressing both derivations as projections
onto it makes parity a property that can be tested once rather than a coincidence of two independent
implementations.

**Alternatives considered**: Allowing arbitrary directory names as versions (rejected: produces
addresses that cannot be compared with reference-derived versions). Normalizing a trailing `.x`
branch to `X.Y.0` (rejected: invents a patch version that does not exist and would collide with a
real `X.Y.0` tag).

## D-04: Selecting the strategy

**Decision**: `site.json` may carry an optional `versions.strategy` key with the value `ref` or
`tree`, defaulting to `ref`. The key is part of the configuration hash. Selection is per site, not per
project.

**Rationale**: The requirement names two implementations without saying how one is chosen, and a
default with an explicit override keeps the common case zero-configuration. Keeping selection out of
the project record protects the onboarding contract.

**Alternatives considered**: Inferring the strategy from whatever the repository happens to contain
(rejected: implicit behavior, and a repository that has both tags and version directories would
resolve differently depending on inspection order). One strategy per project in the custom property
(rejected: onboarding must stay one value with one meaning).

## D-05: How documentation sources are materialized

**Decision**: Sources are read from a git checkout of each project. The build lists references with
`git ls-remote --tags --heads`, and materializes a project checkout once per build under
`<output.cacheDirectory>/sources/<slug>`, then reads the documented refs from it. Git is invoked
through a single wrapper type over `symfony/process`, so no other layer shells out.

**Rationale**: The reference strategy needs reference names; the tree strategy needs a directory
listing at a ref; both strategies need file contents at a ref. One checkout per project serves all
three, while a per-file content API would cost one request per document against a rate-limited
boundary. It also keeps the corpus readable offline once fetched.

**Alternatives considered**: The GitHub contents API per file (rejected: request volume scales with
document count and hits rate limits on a full-corpus build). The tarball endpoint per reference
(rejected: still one archive per version, and it cannot answer reference queries). A single shared
clone with worktrees (rejected: worktree bookkeeping is state that outlives the build and can be left
inconsistent on failure).

## D-06: Output layout, public addresses and stale output

**Decision**: A page's output file is
`<output.directory>/<slug>/<version>/<document path with a trailing /index.html>`, where a document
path of `index.md` maps to the version root `index.html`. A page's public address is
`https://<site.hostname>/<slug>/<version>/<document directory>/`. The output tree is reset at the
start of every build and rewritten from the resolved corpus, so a page that is no longer in the corpus
cannot survive a rebuild.

**Rationale**: `directory/index.html` is what GitHub Pages serves for a clean address, so the file
shape and the address shape are one rule rather than two. Resetting the tree is what makes an
incremental build comparable to a clean build at all; without it, "byte-identical" would only hold
for a corpus that never shrinks. Reset removes output, never sources or cache.

**Alternatives considered**: `.html` files with explicit extensions in the address (rejected: every
internal link would need an extension, and the address would differ from what GitHub Pages serves
for a directory). Leaving stale files in place and comparing only regenerated pages (rejected: the
published site would carry pages for projects that are no longer onboarded).

## D-07: Cache key material and storage

**Decision**: The cache key is a hash of three concatenated components in a fixed order: the source
content hash of the page's source file, the hash of the applied template set (the resolved document
template and layout shell contents, plus the template map itself), and the configuration hash. A
cached page is stored as one file per key under `output.cacheDirectory/pages/<key>.html`. The
configuration hash is a canonical encoding of the site configuration with the cache directory path
excluded, so moving the cache directory does not invalidate it.

**Rationale**: The requirement fixes the three components. Keeping the applied template set in the
key is what makes "template changed" invalidate work even though the configuration did not change.
Excluding the cache directory from its own hash avoids a directory move producing a full rebuild,
which would still be correct but gratuitously expensive. One file per key keeps reuse atomic and
crib-safe to delete.

**Alternatives considered**: A single index file mapping keys to pages (rejected: read-modify-write of
a shared file, and corruption takes the whole cache). A modification-time based key (rejected: times
are not content, and a touched file with the same bytes would rebuild).

## D-08: Report shape, statuses and exit codes

**Decision**: The report holds `exitCode`, `totals` (`ok`, `partial`, `failed`, `skipped`, `pages`)
and `projects` ordered by site path, each with `slug`, `status` and `errors` ordered by reason code.
Exit code `0` means every discovered project rendered completely. Exit code `1` means at least one
project is `partial` or `failed`. Exit code `2` means the build was blocked before rendering
(ambiguous corpus, invalid configuration, unreachable discovery boundary, or a missing site address
file). The report's `exitCode` and the process exit code are always the same value. A configuration
that cannot be read at all exits `2` with a diagnostic and no report, because no report path is
known.

**Rationale**: The publish workflow consumes the report, so the vocabulary has to be closed and
stable. Separating "the corpus is wrong" from "the corpus rendered with defects" lets the workflow
distinguish a fixable content problem from a broken site definition. Ordering makes the report
diffable, which is what makes a failing build reviewable.

**Alternatives considered**: A single non-zero code (rejected: the workflow cannot tell a broken
project from a broken configuration). Writing reason codes only to standard output (rejected: the
workflow consumes the report, not the log).

## D-09: Command surface

**Decision**: One console command, `site:build`, invoked by `bin/aggregate-render`. It takes
`--site=<path>` defaulting to `site.json` at the working directory, and `--json` to print the report
shape instead of the human summary. Standard output carries progress and the summary; standard error
carries diagnostics.

**Rationale**: The publish workflow reuses its existing invocation, so the command must be a single
deterministic entry point with a stable exit code. A human summary is the default per the
constitution, and `--json` gives machine consumers the same data without a file read.

**Alternatives considered**: A Mezzio or Laminas CLI command set (rejected: this package is a build
tool, not a request-handling application, and a framework command runner would add a container
requirement for no behavior). Reading the report path from the environment (rejected: `site.json` is
the single configuration contract).

## D-10: Template override surface

**Decision**: `site.json` may carry `templates.map` as an object of template name to either an
absolute file path or a path relative to the site configuration file. Names not present in the map
resolve through the shared renderer package's default map, so `commondoc::document` can be replaced
without a source change. The map is part of the configuration hash, and the contents of every mapped
template are part of the template hash.

**Rationale**: The override capability is a hard requirement and it needs a configuration surface;
`site.json` is the only configuration file the build owns. Relative paths keep a site repository
portable between a developer machine and the runner.

**Alternatives considered**: An environment variable per template (rejected: unbounded vocabulary).
A convention-based lookup of a fixed filename (rejected: cannot replace more than one template and
cannot be audited in configuration).

## D-11: GitHub boundary and token handling

**Decision**: Discovery lists the organization's repositories, then reads each repository's custom
property values, selecting those with `doc-target`. Requests use a PSR-18 client and PSR-17 factories
injected through the wiring. The token is read from the environment variable named by
`organization.tokenEnvVar` at the moment of use, is sent only in the request header, and is never
logged, written to the report, or written to the output tree. A missing or rejected token blocks the
build with exit code `2`.

**Rationale**: A PSR-18 client keeps the boundary testable with a recorded-response stub and free of a
vendor SDK, which is what lets the integration test assert request shapes. Reading the token at use
time means a rotated token needs no restart of anything.

**Alternatives considered**: A vendor SDK (rejected: dependency weight for two endpoints, and its
error shapes would leak into reason codes). Reading the token from `site.json` (rejected: a secret in
a committed configuration file).

## D-12: Package identity and the namespace split

**Decision**: The package is renamed to `common-doc/aggregate-renderer` with PSR-4 root
`CommonDoc\Aggregate\` to `src/`, and test roots `CommonDocTest\Aggregate\` to `test/unit/` and
`CommonDocTestIntegration\Aggregate\` to `test/integration/`. `CommonDoc\Render\` is never defined in
this repository; it belongs to the shared renderer package. One additive Mago boundary rule records
the split, so a future symbol in the wrong namespace fails the guard gate rather than review.

**Rationale**: The namespace is a settled requirement, and the repository still carries the template
identity (`webware/skeleton`, `Webware\Skeleton`). A guard rule is the only enforcement that survives
a contributor who does not read the plan.

**Alternatives considered**: Keeping the template identity until first release (rejected: every file
written afterwards would need a second pass, and the autoload roots would be wrong in the meantime).
Relying on review alone (rejected: the constitution places boundary rules in the guard gate).
