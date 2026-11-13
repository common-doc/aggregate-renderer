# Tasks: Aggregate Documentation Site Rendering

**Input**: Design documents from `/specs/001-aggregate-renderer/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: Included. The constitution's second principle makes tests mandatory, so every story carries
test tasks and its tests are written first and observed failing before implementation.

**Organization**: Tasks are grouped by user story so each story can be implemented, tested and
delivered on its own.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3, US4, US5)
- Every task names the exact file path it touches

## Delivery rules for every phase

- One phase is one step. Each step lands as its own branch and its own pull request, merged with a
  merge commit. Never squash.
- Never merge a pull request without an explicit instruction.
- No file is deleted, renamed or moved without owner approval. Nothing in this task list moves a file:
  the package identity change lives in manifest keys, and every source and test file is created at its
  final path.
- After each phase, `mago format`, `mago lint`, `mago analyze`, `mago guard` and the affected PHPUnit
  suite must exit zero with no new baseline entry.

## Path Conventions

- Source: `src/` with PSR-4 root `CommonDoc\Aggregate\`
- Unit tests: `test/unit/` with PSR-4 root `CommonDocTest\Aggregate\`
- Integration tests: `test/integration/` with PSR-4 root `CommonDocTestIntegration\Aggregate\`
- Command entry point: `bin/aggregate-render`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Put the package on its own identity so no later task has to be reworked.

- [ ] T001 Change the package manifest in `composer.json`: name `common-doc/aggregate-renderer`, PSR-4 `CommonDoc\Aggregate\` to `src/`, `CommonDocTest\Aggregate\` to `test/unit/`, `CommonDocTestIntegration\Aggregate\` to `test/integration/`, `php` to `~8.4.1 || ~8.5.0 || ~8.6.0`, `config.platform.php` to `8.4.99`, and require `common-doc/commondoc`, `laminas/laminas-view`, `psr/http-client`, `psr/http-factory`, `psr/http-message`, `symfony/console`, `symfony/process`
- [ ] T002 [P] Add the PHP 8.6 matrix leg in `webware-ci.json` while keeping 8.4, 8.5 and `coverage_php_version` at 8.5
- [ ] T003 [P] Add one additive namespace boundary rule in `mago.toml` so a symbol under `CommonDoc\Render\` fails the guard gate; keep the single `php-version` override at `8.4.1` unchanged
- [ ] T004 [P] Repoint the existing test namespaces in `test/unit/ConfigProviderTest.php` and `test/integration/ConfigProviderWiringTest.php` to the new roots
- [ ] T005 Re-run `mago format`, `mago lint`, `mago analyze`, `mago guard` and PHPUnit against the new identity and confirm all exit zero with no new baseline entry

**Checkpoint**: The package resolves, autoloads and passes every gate under its own name.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: The value objects, enums, decision seam and configuration reader that every story uses.

**CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T006 Create the discovered project value object in `src/Discovery/Project.php` with readonly promoted `name`, `owner`, `sitePath` and `docDirectory`
- [ ] T007 [P] Create the target value object in `src/Version/Target.php` holding one project at one version, with no filesystem path and no address
- [ ] T008 [P] Create the decision seam interface in `src/Version/StrategyInterface.php` as `resolve(array $candidates): array` returning `list<Target>`, docblocked as performing no input, no output and no version control operation
- [ ] T009 [P] Create the strategy enum in `src/Version/Strategy.php` with cases `Ref` and `Tree` and wire values `ref` and `tree`
- [ ] T010 [P] Create the project status enum in `src/Build/ProjectStatus.php` with cases `Ok`, `Partial`, `Failed`, `Skipped` and wire values `ok`, `partial`, `failed`, `skipped`
- [ ] T011 [P] Create the page status enum in `src/Build/PageStatus.php` with cases `Rendered` and `Failed`
- [ ] T012 [P] Create the reason code enum in `src/Build/ReasonCode.php` with the thirteen values recorded in `data-model.md`, each with its hyphenated wire value
- [ ] T013 Implement `src/Version/RefStrategy.php` per `contracts/version-strategy.md`: `vX.Y.Z` to `X.Y.Z`, `X.Y.x` to `X.Y`, every other candidate excluded, output ordered by numeric version segments
- [ ] T014 [P] Implement `src/Version/TreeStrategy.php` per `contracts/version-strategy.md`: directory names matching `^\d+\.\d+(\.\d+)?$` accepted verbatim, every other candidate excluded, same ordering
- [ ] T015 Implement `src/Version/StrategyFactory.php` selecting the configured strategy and returning it as `StrategyInterface`
- [ ] T016 [P] Write `test/unit/Version/RefStrategyTest.php` covering a release reference, a maintenance reference, an excluded reference, ordering by numeric segment and an empty candidate list
- [ ] T017 [P] Write `test/unit/Version/TreeStrategyTest.php` covering a version directory, a patch version directory, an excluded directory name, ordering and an empty candidate list
- [ ] T018 Write `test/unit/Version/StrategyParityTest.php` as the single parity test: build one fixture corpus whose references and version directories agree, resolve with both implementations and assert `static::assertSame` on the two lists
- [ ] T019 [P] Write `test/unit/Discovery/ProjectTest.php` asserting the site path grammar invariant and that an empty documentation directory is rejected
- [ ] T020 Create the project source seam in `src/Discovery/ProjectSourceInterface.php` as `all(): array` returning `list<Project>`
- [ ] T021 Create the readonly configuration value object in `src/Config/SiteConfig.php` with the fields and invariants recorded in `data-model.md`
- [ ] T022 Create the configuration failure in `src/Config/InvalidSiteConfig.php` carrying a `ReasonCode` and a message naming the file and the key at fault, never a token
- [ ] T023 Create `src/Config/SiteConfigReader.php` decoding the configuration file, applying the `output.cacheDirectory` default, resolving relative paths against the file's directory and rejecting every invalid case with `invalid-site-config`
- [ ] T024 Create `src/Config/ConfigHash.php` hashing a canonical key-ordered encoding of `SiteConfig` with `cacheDirectory` excluded
- [ ] T025 [P] Write `test/unit/Config/SiteConfigReaderTest.php` covering a minimal valid file, each required key missing, a hostname carrying a scheme, a malformed template map value and an unparsable file
- [ ] T026 [P] Write `test/unit/Config/ConfigHashTest.php` asserting a changed key changes the hash and a changed cache directory does not

**Checkpoint**: The seam, the value objects and the configuration reader are complete and covered.

---

## Phase 3: User Story 1 - Publish one aggregate site for every onboarded project (Priority: P1) MVP

**Goal**: One build renders every discovered project and version into one tree with a site index, a
not-found page and the site address file, and writes a report.

**Independent Test**: Run the build against a fixture project source and assert the tree, the address
of every page and the report totals.

### Tests for User Story 1

> Write these first and confirm they fail before implementing.

- [ ] T027 [P] [US1] Write `test/unit/Render/PageAddressFactoryTest.php` asserting one file and one address per page, the version root mapping and the directory mapping from `contracts/output-tree.md`
- [ ] T028 [P] [US1] Write `test/unit/Report/ReportFileTest.php` asserting the written JSON matches `contracts/build-report.schema.json` and that `exitCode` equals the value in the report
- [ ] T029 [P] [US1] Write `test/integration/Build/SiteBuildTest.php` building a fixture corpus end to end and asserting the file per address, the index, the not-found page, the site address file and the reconciled totals
- [ ] T030 [P] [US1] Write `test/unit/Output/CnameFileTest.php` asserting the written content is the bare hostname plus one trailing newline and that an absent file is reported as `missing-cname`

### Implementation for User Story 1

- [ ] T031 [P] [US1] Create the page address value object in `src/Render/PageAddress.php` holding the output file and the public address as one indivisible pair
- [ ] T032 [US1] Create `src/Render/PageAddressFactory.php` deriving both members of `PageAddress` from one `Target` and one document path
- [ ] T033 [P] [US1] Create the page result in `src/Build/PageResult.php` with status, optional reason code and reuse flag
- [ ] T034 [P] [US1] Create the project result in `src/Build/ProjectResult.php` with site path, status, pages and ordered reason codes
- [ ] T035 [P] [US1] Create the totals value object in `src/Build/Total.php` with the five counters as `non-negative-int`
- [ ] T036 [US1] Create the build report in `src/Build/BuildReport.php` with `exitCode`, totals and projects ordered by site path
- [ ] T037 [US1] Create `src/Report/ReportFile.php` writing the report JSON with LF endings and deterministic key order
- [ ] T038 [P] [US1] Create `src/Output/OutputTree.php` resetting the output directory and providing deterministic write and read paths
- [ ] T039 [US1] Create `src/Output/CnameFile.php` writing the site address file from the configured hostname and verifying its presence
- [ ] T040 [P] [US1] Create the source seam in `src/Source/SourceInterface.php` exposing candidate names, source paths and source contents for one project
- [ ] T041 [US1] Create `src/Source/GitCommand.php` wrapping one `symfony/process` invocation with an argument list, never a shell string
- [ ] T042 [US1] Create `src/Source/GitSource.php` listing references, materializing one project checkout under the cache directory and reading documented refs
- [ ] T043 [P] [US1] Create `src/Render/DocumentReader.php` turning markdown source into a CommonMark `Document`
- [ ] T044 [US1] Create `src/Render/ViewFactory.php` building the PhpRenderer with the resolved template map so `commondoc::document` is the default document template
- [ ] T045 [US1] Create `src/Render/TargetRenderer.php` rendering every page of one `Target` through the shared document seam and recording `PageResult`
- [ ] T046 [P] [US1] Create `src/Render/SiteIndex.php` rendering the site index listing every project and version with its address
- [ ] T047 [P] [US1] Create `src/Render/NotFoundPage.php` rendering the not-found page and writing it as `404.html`
- [ ] T048 [US1] Create `src/Render/SiteRenderer.php` rendering every project in the corpus and collecting results in deterministic order
- [ ] T049 [US1] Create `src/Build/SiteBuild.php` sequencing configure, discover, resolve, check, materialize, render, write extras, verify site address file and write report
- [ ] T050 [US1] Create `src/Console/BuildCommand.php` as `site:build` with `--site` and `--json`, printing a per-project line and a summary by default
- [ ] T051 [US1] Create the entry point `bin/aggregate-render` passing argv to the command and returning its exit code
- [ ] T052 [US1] Contribute the factories for the command, the source, the renderer and the report writer in `src/ConfigProvider.php`

**Checkpoint**: A fixture corpus builds into a complete, addressable site. This is the MVP.

---

## Phase 4: User Story 2 - Refuse to publish an ambiguous or incomplete site (Priority: P1)

**Goal**: Corpus and configuration defects stop the build before any page is written, and a defective
page degrades its project without blocking the others.

**Independent Test**: Introduce each defect against the fixture corpus and assert the exit code, the
report reason codes and the absence or presence of pages.

### Tests for User Story 2

- [ ] T053 [P] [US2] Write `test/unit/Build/CorpusCheckTest.php` covering `duplicate-slug`, `duplicate-version`, `duplicate-page-address` and a clean corpus producing no findings
- [ ] T054 [P] [US2] Write `test/integration/Build/BlockedBuildTest.php` asserting that a blocked build writes no page, records the reason codes with per-project attribution and exits `2`
- [ ] T055 [P] [US2] Write `test/unit/Build/BuildReportTest.php` asserting the exit code derivation: `0` for all ok, `1` for any partial or failed, `2` when rendering never started
- [ ] T056 [P] [US2] Write `test/integration/Build/PartialBuildTest.php` asserting one unparsable document leaves its project `partial`, every other project `ok`, and `totals.pages` counting only written pages

### Implementation for User Story 2

- [ ] T057 [US2] Create `src/Build/CorpusCheck.php` returning reason codes attributed by site path for duplicate site paths, duplicate versions and duplicate page addresses
- [ ] T058 [US2] Run `CorpusCheck` in `src/Build/SiteBuild.php` before materializing any source, so a duplicate site path never reaches rendering
- [ ] T059 [US2] Derive project status in `src/Build/ProjectResult.php` from the page results and reason codes so `Ok`, `Partial`, `Failed` and `Skipped` satisfy the invariants in `data-model.md`
- [ ] T060 [US2] Derive the report exit code in `src/Build/BuildReport.php` and make the command return that same value from `src/Console/BuildCommand.php`
- [ ] T061 [US2] Verify the site address file in `src/Output/CnameFile.php` before the output tree is handed to publish, failing with `missing-cname`
- [ ] T062 [US2] Record the reason codes that block a build for the projects never reached, marking them `Skipped`, in `src/Build/SiteBuild.php`
- [ ] T063 [US2] Print each defect with its project, its reason code and its message in the summary of `src/Console/BuildCommand.php`

**Checkpoint**: A defective corpus fails loudly and completely; a defective page fails narrowly.

---

## Phase 5: User Story 3 - Onboard a project with one repository property (Priority: P2)

**Goal**: Discovery reads the repository custom property and nothing else, so onboarding is one
property and no per-project workflow change.

**Independent Test**: Run discovery against recorded responses, with and without the property, and
assert the resulting project list.

### Tests for User Story 3

- [ ] T064 [P] [US3] Write `test/integration/Discovery/GitHubProjectSourceTest.php` against recorded responses covering a repository with the property, one with an empty value, one with no property, and a rejected credential
- [ ] T065 [P] [US3] Write `test/unit/Discovery/SitePathTest.php` covering uppercase, spaces, consecutive separators, leading and trailing separators and two distinct names deriving the same site path

### Implementation for User Story 3

- [ ] T066 [P] [US3] Create `src/Discovery/SitePath.php` deriving the site path from a repository name and rejecting a name that derives nothing
- [ ] T067 [US3] Create `src/Discovery/DiscoveryFailed.php` carrying a `ReasonCode` for a missing token, a rejected token or an unreachable boundary
- [ ] T068 [US3] Create `src/Discovery/GitHubProjectSource.php` listing organization repositories, reading each `doc-target` property and returning only onboarded projects
- [ ] T069 [US3] Create `src/Discovery/Token.php` reading the value of the configured environment variable at the moment of use and never retaining it in a value that reaches output or the report
- [ ] T070 [US3] Bind `ProjectSourceInterface` to `GitHubProjectSource` with the injected PSR-18 client and PSR-17 factories in `src/ConfigProvider.php`

**Checkpoint**: A new project appears in the next build after one property change and nothing else.

---

## Phase 6: User Story 4 - Rebuild incrementally for the identical site (Priority: P2)

**Goal**: A warm build reuses prior work and still produces a tree byte-identical to a clean build.

**Independent Test**: Build clean, copy the tree, build warm and compare the two trees; then change one
document, then one template, then one configuration value and compare each time.

### Tests for User Story 4

- [ ] T071 [P] [US4] Write `test/unit/Cache/CacheKeyTest.php` asserting a change to any of the three components changes the key and that nothing else does
- [ ] T072 [P] [US4] Write `test/unit/Render/TemplateHashTest.php` asserting the hash covers the applied document template, the layout shell and the template map
- [ ] T073 [P] [US4] Write `test/integration/Build/IncrementalBuildTest.php` asserting the warm tree is byte-identical to the clean tree, that a source change changes only its own page and that a template change changes every page

### Implementation for User Story 4

- [ ] T074 [P] [US4] Create `src/Render/TemplateHash.php` hashing the resolved document template, layout shell and template map contents
- [ ] T075 [US4] Create `src/Cache/CacheKey.php` combining the source hash, the template hash and the configuration hash in that fixed order
- [ ] T076 [US4] Create `src/Cache/RenderCache.php` storing one file per key under the cache directory and returning a cached page only on an exact key match
- [ ] T077 [US4] Consult `RenderCache` in `src/Render/TargetRenderer.php` before rendering and record reuse on `PageResult`
- [ ] T078 [US4] Wire the configured cache directory into `RenderCache` in `src/ConfigProvider.php`
- [ ] T079 [US4] Report the reuse count in the summary of `src/Console/BuildCommand.php` without changing the report schema

**Checkpoint**: A warm build is indistinguishable from a clean build and measurably cheaper.

---

## Phase 7: User Story 5 - Give a site its own document styling (Priority: P3)

**Goal**: A site replaces the document template through configuration alone, with no source change.

**Independent Test**: Add a `templates.map` entry pointing at a file, rebuild without touching source
and assert every page renders through the override.

### Tests for User Story 5

- [ ] T080 [P] [US5] Write `test/unit/Render/TemplateMapTest.php` asserting a mapped name wins, an unmapped name falls through to the shared default, and a missing mapped file is rejected
- [ ] T081 [P] [US5] Write `test/integration/Render/TemplateOverrideTest.php` asserting every page of a fixture corpus renders through a mapped `commondoc::document` with no source change

### Implementation for User Story 5

- [ ] T082 [P] [US5] Create `src/Render/TemplateMap.php` resolving configuration entries into template name to absolute file path
- [ ] T083 [US5] Resolve the template map in `src/Render/ViewFactory.php` so a configured name replaces the shared default and an unconfigured name keeps it
- [ ] T084 [US5] Reject a map entry whose file cannot be read with `invalid-site-config` in `src/Config/SiteConfigReader.php`

**Checkpoint**: Custom site styling is a configuration change inside the same published tree.

---

## Phase 8: Polish and Cross-Cutting Concerns

**Purpose**: Documentation, secret hygiene and the remaining gates.

- [ ] T085 [P] Document installation, configuration and the command in `README.md`
- [ ] T086 [P] Add a test asserting no token value appears in the output tree or the report in `test/integration/Build/SecretHygieneTest.php`
- [ ] T087 Run every scenario in `quickstart.md` against a fixture corpus and record the outcome
- [ ] T088 Run `vendor/bin/infection --min-msi=95 --min-covered-msi=95` and add focused tests until both thresholds pass
- [ ] T089 Run `mago format`, `mago lint`, `mago analyze` and `mago guard` and confirm all exit zero with no new baseline entry
- [ ] T090 Confirm the report written by the build validates against `contracts/build-report.schema.json` and that every settled field name is present

---

## Dependencies and Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies, can start immediately.
- **Foundational (Phase 2)**: Depends on Setup. Blocks every user story.
- **User Stories (Phase 3 onward)**: All depend on Foundational.
  - US1 and US2 are both P1. US2 hardens the build US1 creates, so US1 lands first.
  - US3, US4 and US5 each extend a seam that already exists after Foundational and US1.
- **Polish (Phase 8)**: Depends on every story that is being delivered.

### User Story Dependencies

- **US1 (P1)**: After Foundational. No dependency on another story. Uses a fixture project source, so
  it does not need US3.
- **US2 (P1)**: After US1, because its checks wrap the build sequence.
- **US3 (P2)**: After Foundational. Independently testable through the source seam; it replaces the
  fixture source with the real boundary.
- **US4 (P2)**: After US1. Its template hash takes the applied template set from US1 and is completed
  by US5 when a site supplies its own map.
- **US5 (P3)**: After US1. Independent of US4 except that both read the same template map.

### Within Each User Story

- Tests are written and observed failing before implementation.
- Value objects before the types that combine them.
- Factories before the actors that use them.
- A collaborator is injected through its factory; no actor constructs another actor.
- Wiring changes come last in a story, once every class exists.

### Parallel Opportunities

- Setup: T002, T003 and T004 are independent files.
- Foundational: T007 through T012 are independent files, and T014 is independent of T013.
- US1: T031, T033, T034, T035, T038, T040, T043, T046 and T047 touch distinct files.
- US2: T053 through T056 are four independent test files.
- US3: T064 and T065 are independent, and T066 is independent of T067.
- US4: T071, T072 and T073 are independent, and T074 is independent of T075.
- US5: T080, T081 and T082 are independent.

---

## Parallel Example: User Story 1

```text
Task: "Write test/unit/Render/PageAddressFactoryTest.php asserting one file and one address per page"
Task: "Write test/unit/Report/ReportFileTest.php asserting the written JSON matches the report schema"
Task: "Write test/integration/Build/SiteBuildTest.php building a fixture corpus end to end"
Task: "Create src/Render/PageAddress.php holding the output file and the public address as one pair"
Task: "Create src/Build/Total.php with the five counters as non-negative-int"
```

---

## Implementation Strategy

### MVP first

1. Phase 1: Setup.
2. Phase 2: Foundational.
3. Phase 3: US1.
4. Stop and validate US1 against the fixture corpus. A complete, addressable site is the point at which
   the package does something useful.

### Incremental delivery

1. Setup and Foundational land, every gate green.
2. US1 lands: one aggregate site is produced.
3. US2 lands: the build refuses an ambiguous corpus.
4. US3 lands: real discovery replaces the fixture source.
5. US4 lands: warm builds are cheap and identical.
6. US5 lands: a site can bring its own document template.
7. Polish lands: documentation, secret hygiene and the final gates.

Each step is its own branch and its own merge-commit pull request. Nothing is merged without an
explicit instruction.

---

## Notes

- `[P]` marks tasks that touch different files and share no dependency.
- `[Story]` maps a task to the user story it delivers, for traceability.
- Every test class carries `#[CoversClass]` per class under test and `#[CoversMethod]` per method
  exercised, uses `#[Test]`, `static::assert*`, and `createStub()` for every value double.
- Universal types list `null` last; no union type is written as a nullable shorthand.
- No name carries a `Holder`, `Manager`, `Helper`, `Util` or `Service` suffix, and every name is
  singular.
- Types are declared to match the structure and each Mago gate is run once to confirm. Code is never
  reshaped because an analyzer complained.
- Avoid: tasks that edit the same file in the same phase, and cross-story tasks that break a story's
  independent testability.
