# Implementation Plan: Aggregate Documentation Site Rendering

**Branch**: `001-aggregate-renderer` | **Date**: 2026-11-12 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-aggregate-renderer/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Build a package that renders an entire documentation site in one pass: discover every onboarded
project from a GitHub repository custom property, resolve the versions to publish, materialize each
document, render it through the `common-doc/commondoc` document seam, and write one output tree that
carries every project and version plus a site index, a not-found page and a site address file. The
build writes a machine-readable report, refuses to render when the corpus is ambiguous, and reuses
prior rendering work behind a key built from the source hash, the template hash and the configuration
hash so that a warm build is byte-identical to a clean build.

The technical approach follows four separations:

1. Discovery is a GitHub API concern; version decision is a pure seam with two interchangeable
   implementations; source materialization is a git concern; rendering is the shared document seam.
2. `Target` is the single value that names one project at one version. The output file and the public
   address for every page are both derived from it by one factory, so they cannot diverge.
3. Integrity checks (duplicate site path, duplicate version, missing site address, invalid
   configuration) run before any page is written, and produce a report rather than a half-published
   tree.
4. Caching changes only which work is repeated. Output is written from the same deterministic path in
   every run, and the output tree is rebuilt in place from the resolved corpus, so stale pages cannot
   survive a rebuild.

## Technical Context

**Language/Version**: PHP 8.4 runtime floor, platform pinned to `8.4.99` for resolution, supporting
`~8.4.1 || ~8.5.0 || ~8.6.0`

**Primary Dependencies**: `common-doc/commondoc` (document rendering seam, page object, page context,
document template, layout shell, template map), `league/commonmark` (markdown to document tree),
`laminas/laminas-view` (view renderer hosting the template map), `psr/http-client`,
`psr/http-factory`, `psr/http-message` (GitHub API boundary), `laminas/laminas-diactoros` (default
PSR-17 implementation supplied by the wiring), `symfony/console` (single command entry point),
`symfony/process` (git invocation)

**Storage**: Filesystem only. Output tree at `output.directory`, report at `output.reportPath`,
render cache at `output.cacheDirectory`, project checkouts under the cache directory.

**Testing**: PHPUnit 13 with `requireCoverageMetadata`, unit suite under `test/unit`, integration
suite under `test/integration`; Infection with `min_msi` and `min_covered_msi` of 95

**Target Platform**: Command-line execution on the CI runner (Linux) and on developer containers;
PHP 8.4 and 8.5 matrix legs

**Project Type**: Library plus single-command CLI

**Performance Goals**: A clean build of the full organization corpus completes inside the publish
workflow job budget; an incremental build reuses every unaffected page; per-project progress is
emitted as the build proceeds so a long run is never silent

**Constraints**: Strategy implementations perform no input or output and no version control
operation; incremental output is byte-identical to clean output; all file writes use LF endings and
deterministic ordering; no secret is written to the output tree or the report; the install baseline
of `vendor/webware/webware-tools` provides all Mago configuration

**Scale/Scope**: The organization corpus of roughly 25 projects, each publishing one to six versions,
with tens to low hundreds of documents per version

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status | Evidence |
|-----------|------|--------|----------|
| I. Code Quality | Namespace `CommonDoc\Aggregate\` only; typed, readonly value objects; enum over constants; constructor property promotion; no banned suffixes; Mago `format`, `lint`, `analyze`, `guard` exit zero with no new baseline entry | PASS | Every type in the structure below sits under `CommonDoc\Aggregate\` and collides with nothing in `CommonDoc\Render\`. Closed sets (project status, page status, reason code) are enums. Value objects are `readonly` with promoted constructor properties. |
| I. Code Quality | Central tooling is extended, never overridden; a local rule strengthens the centre | PASS | `mago.toml` keeps its single `php-version` override and gains at most one additive boundary rule for the namespace split, recorded as a task. |
| II. Testing Standards | `#[Test]`, `#[CoversClass]` per class, `#[CoversMethod]` per method, `static::assert*`, `createStub()` for value doubles, Infection at 95 | PASS | Test layout mirrors `src/`, and the task list carries metadata and threshold tasks before story completion. |
| II. Testing Standards | Integration coverage for every external boundary | PASS | GitHub discovery and git materialization are integration-tested against recorded responses and a fixture repository; the end-to-end build is an integration test. |
| III. User Experience Consistency | ConfigProvider contributes wiring, host merges it; human-readable output by default with structured output behind a flag; actionable error text; no internal storage types leaked | PASS | `src/ConfigProvider.php` contributes factories only. The command prints a per-project summary by default and emits the report shape with `--json`. Reason codes are public vocabulary; exception messages name the file and the key, not stack internals. |
| IV. Performance Requirements | CLI responds within 500ms for typical operations; network operations show progress beyond one second; memory bounded and proportional to input | PASS | The command validates arguments and configuration in well under 500ms. A site build is a batch operation and emits per-project progress, satisfying the progress rule. Pages are streamed one at a time; no run holds the whole corpus in memory. |
| IV. Performance Requirements | No benchmark regression where benchmarks ship | N/A | The package ships no PHPBench suite in this feature. `phpbench.json.dist` stays as delivered. |
| Development Workflow | Every change lands through a pull request; merge commit, never squash; no file deleted, renamed or moved without owner approval | PASS | The task list is grouped into steps that each land as one merge-commit pull request. The package rename (`composer.json` name, PSR-4 roots) is called out as a step requiring owner approval because it moves files. |

Post-design re-check: the Phase 1 artifacts add no dependency outside the list above, add no
namespace outside `CommonDoc\Aggregate\`, and introduce no mutable shared state. All gates remain
PASS.

## Project Structure

### Documentation (this feature)

```text
specs/001-aggregate-renderer/
├── plan.md              # This file (/speckit-plan command output)
├── spec.md              # Feature specification (/speckit-specify command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
│   ├── build-report.schema.json
│   ├── output-tree.md
│   ├── publish-workflow.md
│   ├── site-config.schema.json
│   └── version-strategy.md
├── checklists/
│   └── requirements.md  # Spec quality checklist (/speckit-specify command output)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
bin/
└── aggregate-render                    # Command entry point, argv only

src/
├── Build/
│   ├── BuildReport.php                 # Readonly report value object
│   ├── CorpusCheck.php                 # Pre-render ambiguity checks
│   ├── PageResult.php                  # One rendered page result
│   ├── PageStatus.php                  # Rendered, failed
│   ├── ProjectResult.php               # One project result with reason codes
│   ├── ProjectStatus.php               # ok, partial, failed, skipped
│   ├── ReasonCode.php                  # Closed set of public reason codes
│   ├── SiteBuild.php                   # Orchestrates one build, returns BuildReport
│   └── Total.php                       # Readonly totals value object
├── Cache/
│   ├── CacheKey.php                    # Source hash, template hash, config hash
│   └── RenderCache.php                 # File-backed reuse of rendered pages
├── Config/
│   ├── ConfigHash.php                  # Canonical hash of SiteConfig
│   ├── InvalidSiteConfig.php           # Configuration failure with a reason code
│   ├── SiteConfig.php                  # Readonly site configuration
│   └── SiteConfigReader.php            # Reads and validates site.json
├── Console/
│   └── BuildCommand.php                # site:build, human summary or --json
├── Discovery/
│   ├── GitHubProjectSource.php         # Org repositories and doc-target property
│   ├── Project.php                     # Readonly discovered project
│   ├── ProjectSourceInterface.php      # all(): list<Project>
│   ├── SitePath.php                    # Derives a site path from a repository name
│   └── Token.php                       # Reads the token from its environment variable at use
├── Exception/
│   └── DiscoveryException.php          # Discovery failure with a reason code
├── Output/
│   ├── CnameFile.php                   # Writes and verifies the site address file
│   └── OutputTree.php                  # Resets, writes and reads output paths
├── Render/
│   ├── DocumentReader.php              # Source content to CommonMark document
│   ├── NotFoundPage.php                # Renders and writes the not-found page
│   ├── PageAddress.php                 # Output file and public address pair
│   ├── PageAddressFactory.php          # Derives both from one Target
│   ├── SiteIndex.php                   # Renders and writes the site index
│   ├── SiteRenderer.php                # Renders every project in the corpus
│   ├── TargetRenderer.php              # Renders every page of one Target
│   ├── TemplateHash.php                # Hash of the applied template set
│   ├── TemplateMap.php                 # Template name to absolute file path
│   └── ViewFactory.php                 # PhpRenderer with the resolved template map
├── Report/
│   └── ReportFile.php                  # Writes the report JSON
├── Source/
│   ├── GitCommand.php                  # Wraps one git invocation
│   ├── GitSource.php                   # Materializes and reads a project's sources
│   └── SourceInterface.php             # Candidates, paths and contents
├── Version/
│   ├── RefStrategy.php                 # Versions from reference names
│   ├── StrategyFactory.php             # Builds the configured strategy per project
│   ├── StrategyInterface.php           # resolve(list<string>): list<Target>
│   ├── Strategy.php                    # Ref, Tree
│   ├── Target.php                      # One project at one version
│   └── TreeStrategy.php                # Versions from directory names
└── ConfigProvider.php                  # Wiring entry point

test/
├── integration/
│   ├── Build/
│   │   ├── BlockedBuildTest.php        # Defects stop the build before rendering
│   │   ├── IncrementalBuildTest.php    # Warm tree equals clean tree
│   │   ├── PartialBuildTest.php        # One unparsable document degrades one project
│   │   ├── SecretHygieneTest.php       # No token in the output tree or the report
│   │   └── SiteBuildTest.php           # End-to-end build over a fixture corpus
│   ├── Discovery/
│   │   └── GitHubProjectSourceTest.php # Recorded-response discovery contract
│   └── Render/
│       └── TemplateOverrideTest.php    # Mapped document template, no source change
└── unit/
    ├── Build/
    ├── Cache/
    ├── Config/
    ├── Console/
    ├── Discovery/
    ├── Output/
    ├── Render/
    ├── Report/
    ├── Source/
    └── Version/
```

**Structure Decision**: Single package layout. The repository is one Composer package with one PSR-4
root (`CommonDoc\Aggregate\` to `src/`) and two test roots, which is the layout the repository
already uses. Layers are directories, not sibling packages: `Discovery` performs the only network
read, `Source` the only version control read, `Version` is the pure decision seam, `Render` owns the
shared renderer boundary, `Output`, `Cache` and `Report` own the filesystem, and `Console` is the
single entry point.

## Phase 0 and Phase 1 Outputs

- [research.md](./research.md): every decision the settled requirements left open, with rationale and
  the alternatives considered.
- [data-model.md](./data-model.md): entities, fields, invariants and status transitions.
- [contracts/](./contracts/): site configuration schema, report schema, output tree and URL mapping,
  version resolution contract, and the consumed publish workflow contract.
- [quickstart.md](./quickstart.md): runnable validation scenarios for each user story.

## Complexity Tracking

No constitution violations. No entries.
