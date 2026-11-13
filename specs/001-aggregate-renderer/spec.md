# Feature Specification: Aggregate Documentation Site Rendering

**Feature Branch**: `001-aggregate-renderer`

**Created**: 2026-11-12

**Status**: Draft

**Input**: User description: "Render a whole documentation site: discover every onboarded Webinertia project, resolve which versions to build, render each document through common-doc/commondoc, and write one aggregate tree carrying every project and version, published to GitHub Pages."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Publish one aggregate site for every onboarded project (Priority: P1)

An operator triggers a single build. Every onboarded project, at every version the organization still publishes, is rendered and written into one output tree. Each source document becomes exactly one page with exactly one public address, and the published site carries every project and every version side by side.

**Why this priority**: This is the product. Without a complete and correct aggregate tree there is nothing to publish, and the retired per-project publishing path cannot be retired.

**Independent Test**: Build against two onboarded projects that each resolve two versions. Confirm the output tree carries both projects at both versions, that the reported page total equals the number of source documents, and that every reported page is reachable from the generated site index.

**Acceptance Scenarios**:

1. **Given** two onboarded projects with resolvable versions, **When** the build completes, **Then** the output tree contains every project and version pairing and the report page total equals the number of source documents rendered.
2. **Given** a document at a nested path inside a project, **When** the build completes, **Then** exactly one output file exists for it under that project and version, and exactly one public address serves it.
3. **Given** every project renders without error, **When** the build completes, **Then** the output tree contains a site index naming every project and a not-found page.
4. **Given** a project that publishes several versions, **When** the build completes, **Then** each version is a distinct section of the site and no page is shared between versions.

---

### User Story 2 - Refuse to publish an ambiguous or incomplete site (Priority: P1)

An operator needs the build to stop or report rather than publish something wrong. Two projects that resolve to the same site path is a build error raised before any page is written. A build whose output tree has no site address file is a failure, not a silently broken custom domain. Every run leaves a machine-readable report that states the outcome per project.

**Why this priority**: Publishing an ambiguous tree destroys existing public addresses and cannot be undone by rebuilding, so the guard rails are as important as the rendering itself.

**Independent Test**: Construct a corpus with two projects resolving to the same site path and confirm the build stops before rendering with a named reason code and a non-zero exit code. Then remove the site address from the output tree of an otherwise successful build and confirm the build fails.

**Acceptance Scenarios**:

1. **Given** two onboarded projects that resolve to the same site path, **When** the build runs, **Then** it stops before writing any page, records a named reason code in the report, and exits non-zero.
2. **Given** a completed output tree with no site address file, **When** the build verifies the tree before publish, **Then** the build fails and names the reason code.
3. **Given** one project fails while others render, **When** the build completes, **Then** every other project is still rendered, the failing project is reported as failed or partial with a reason code, and the exit code is non-zero.
4. **Given** a project that cannot be discovered, **When** the build completes, **Then** it is reported as skipped, not failed, and the build exit code is unaffected.
5. **Given** any run, successful or not, **When** the run ends, **Then** the report exists at the configured path and its per-project statuses reconcile with the output tree.

---

### User Story 3 - Onboard a project with one repository property (Priority: P2)

A maintainer onboards a repository by setting the organization's documentation target property on it. The next build includes the project with no edit to the build workflow, no edit to site configuration, and no per-project file. Clearing the property removes the project from the next build.

**Why this priority**: Onboarding friction is what the retired setup got wrong. One property is the whole onboarding contract, so it must be shown to work before more projects migrate.

**Independent Test**: Set the property on a repository that was not previously built, rebuild, and confirm the project appears with no other change in the workspace. Clear the property, rebuild, and confirm it is skipped and reported.

**Acceptance Scenarios**:

1. **Given** a repository with the documentation target property set, **When** the build runs, **Then** the project is discovered and rendered without any other change in the workspace.
2. **Given** a repository without the property, **When** the build runs, **Then** the project is not rendered and is reported as skipped with a reason code.
3. **Given** a project whose property was cleared since the last build, **When** the build runs, **Then** the published tree no longer carries that project.

---

### User Story 4 - Rebuild incrementally for the identical site (Priority: P2)

An operator rebuilds after changing one document, one template or the site configuration. The build reuses prior work where nothing relevant changed, and the resulting tree is byte-identical to what a clean build of the same inputs would have produced.

**Why this priority**: Full rebuilds of every project and version are the dominant cost of publishing. Caching is only acceptable if it can never publish a different site than a clean build.

**Independent Test**: Build twice with a warm cache and no input change and compare every file byte for byte against a clean build. Then change one document and confirm only affected pages differ from the previous output.

**Acceptance Scenarios**:

1. **Given** a warm cache and unchanged sources, templates and configuration, **When** the build runs, **Then** every output file is byte-identical to a clean build of the same inputs.
2. **Given** one document changed, **When** the build runs, **Then** that document's page is regenerated and every unrelated page is byte-identical to the previous output.
3. **Given** the document template changed, **When** the build runs, **Then** every page rendered through that template is regenerated.
4. **Given** the site configuration changed, **When** the build runs, **Then** no page is reused from before that change.
5. **Given** a cache directory that does not exist, **When** the build runs, **Then** the build succeeds and produces the same tree a clean build produces.

---

### User Story 5 - Give a site its own document styling (Priority: P3)

A maintainer gives a documentation site custom styling by supplying a replacement for the document view template. This is a configuration and template change only; no source change and no new release are required, and removing the replacement restores the shared template.

**Why this priority**: Custom styling is the reason sites are differentiated at all, but it is only useful once rendering and publishing already work.

**Independent Test**: Supply a replacement document template in site configuration, rebuild, and confirm rendered pages use it. Remove it, rebuild, and confirm rendered pages are byte-identical to the shared template output.

**Acceptance Scenarios**:

1. **Given** a site configuration that maps the document template to a site-owned file, **When** the build runs, **Then** rendered pages use that file.
2. **Given** no site-owned document template, **When** the build runs, **Then** rendered pages use the shared template.
3. **Given** a site-owned document template that changed since the last build, **When** the build runs, **Then** pages rendered through it are regenerated rather than reused from cache.

---

### Edge Cases

- A project resolves to zero versions because it has no published versions yet: it is skipped with a reason code and does not fail the build.
- A version resolves but contains no documents: it contributes no pages and is reported, rather than silently disappearing.
- Two organizations own a repository with the same name: the resolved site path collides and the build fails before rendering.
- A repository is renamed or transferred: the site path changes, and the newly published tree no longer serves the old address. The build does not retain pages for projects that are no longer discovered.
- The organization access token is missing or rejected: discovery cannot proceed, so the build fails before any rendering and names the reason code.
- The configured site hostname is empty or missing: no site address file can be written, so the build fails.
- The site configuration cannot be read at all: no report path is known, so the build exits with a diagnostic before writing any output and no report is produced.
- A single document fails to render while others in the same project succeed: the project is reported as partial, its successful pages are still written, and the exit code is non-zero.
- A version name repeats within one project after resolution: the build fails with a named reason code.
- Two versions of the same project contain documents at the same path: each stays in its own version section, so no output file is overwritten.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The build MUST discover projects from the organization's documentation target repository custom property, and that property MUST be the only onboarding requirement for a project.
- **FR-002**: The build MUST exclude any repository that does not carry the documentation target property, and MUST record it as skipped with a reason code rather than failing.
- **FR-003**: The build MUST resolve, for every discovered project, the set of versions to render, and MUST express that resolution as a decision that performs no input or output of its own and no version control operation.
- **FR-004**: The build MUST provide at least two interchangeable version resolution approaches, one deriving versions from version reference names and one deriving versions from version directory names, and both MUST produce identical resolved versions for the same corpus.
- **FR-005**: Every rendered page MUST have exactly one output file and exactly one public address, and both MUST be produced from the same resolved project and version pairing so they cannot disagree.
- **FR-006**: The build MUST render each document through the shared document renderer, so that page structure and behavior are identical to single-document rendering.
- **FR-007**: The output tree MUST contain a site index naming every rendered project and a not-found page, both rendered through the same rendering path as documentation pages.
- **FR-008**: The output tree MUST contain a site address file derived from the configured site hostname, and its absence before publish MUST fail the build.
- **FR-009**: The build MUST read its configuration from a single workspace-level site configuration document supplying the organization token environment variable name, the output directory, the report path, the cache directory and the site hostname.
- **FR-010**: The build MUST write a machine-readable report at the configured report path on every run that reads a configuration, carrying an exit code, totals for successful, partial, failed and skipped projects plus total pages, and per project the site path, the status and any reason codes.
- **FR-011**: The report exit code and the process exit code MUST agree: zero only when no project failed or came back partial.
- **FR-012**: The build MUST detect two projects resolving to the same site path before rendering starts, and MUST fail with a named reason code.
- **FR-013**: The build MUST detect two versions resolving to the same version name within one project and MUST fail with a named reason code.
- **FR-014**: The build MUST produce an output tree that is byte-identical whether the cache was cold or warm, given identical sources, templates and configuration.
- **FR-015**: The build MUST key reused work on the source content hash, the template content hash and the configuration hash together, and MUST NOT reuse work when any of the three changed.
- **FR-016**: The build MUST store cached work under the configured cache directory and MUST NOT require the cache directory to exist before the first run.
- **FR-017**: A site MUST be able to replace the shared document template through the site configuration's template map with no source change, and the applied template set MUST participate in the configuration hash.
- **FR-018**: The build MUST report a project as partial when some of its pages render and others fail, and MUST report a project as failed when none of its pages render.
- **FR-019**: The build MUST continue rendering remaining projects after one project fails, so a single broken project does not withhold the rest of the site.
- **FR-020**: The build MUST NOT write any page when a pre-render integrity check fails.
- **FR-021**: The build MUST write every file with normalized line endings and stable ordering so two builds of identical inputs produce identical bytes.
- **FR-022**: The published tree MUST satisfy the existing publish workflow's site configuration, report and site address file contracts, so the workflow is reused unchanged.

### Key Entities

- **Project**: One onboarded repository. Carries its repository identity, its resolved site path, the location of its documentation sources, and its resolved versions.
- **Target**: One project at one version. Carries the source location to render from and the pair of output location and public address that must never diverge.
- **Page**: One rendered document. Carries its source path, its output file, its public address and its render status.
- **Site configuration**: The workspace-level settings naming the token environment variable, the output directory, the report path, the cache directory, the site hostname and any template replacements.
- **Build report**: The machine-readable outcome of one run: exit code, totals, and per project status with reason codes.
- **Cache entry**: A unit of reused rendering output, identified by the content hash of its source, the content hash of its template and the configuration hash.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100 percent of rendered documents produce exactly one output file and exactly one public address, measured as zero duplicate output paths and zero addresses with no file.
- **SC-002**: An incremental rebuild after one document change produces output byte-identical to a clean build of the same inputs for 100 percent of files.
- **SC-003**: Onboarding a new project requires exactly one change, the repository custom property, and zero changes to the publish workflow or the site configuration.
- **SC-004**: A build with duplicate site paths writes zero pages.
- **SC-005**: Every run, including a failing run, leaves a report whose project statuses and page total reconcile with the files present in the output tree.
- **SC-006**: Custom document styling is delivered with zero source changes, verified by an unchanged working tree apart from the configuration and template files.
- **SC-007**: A clean build of the full organization corpus finishes within the publish workflow's job budget, verified by a full-corpus build completing inside the workflow timeout.
- **SC-008**: A project that fails to render never blocks the remaining projects from being published, verified by a build with one deliberately broken project still publishing every other project.

## Assumptions

- The documentation target custom property carries the repository-relative directory that holds that project's documentation sources. Its presence marks the project as onboarded; its value locates the sources. A property with an empty value is treated as absent.
- A project's site path is derived from its repository name, normalized to lowercase with separator runs collapsed. It is not configurable per project, because per-project configuration would end the one-property onboarding contract.
- Version reference names follow the organization's naming: release references of the form `vX.Y.Z` resolve to version `X.Y.Z`, and maintenance branch references of the form `X.Y.x` resolve to version `X.Y`. Version directory names resolve to the version they are named after, so both resolution approaches agree on the corpus the parity check uses.
- Version reference names that do not match either form are not versions and are excluded by the reference-based approach.
- The shared document rendering seam already exists and works: single-document rendering, the page object, the page context, the document template, the layout shell and the template map are provided by the shared renderer package and are out of scope here.
- The publish workflow from the retired setup is reused unchanged. It consumes the site configuration, the report and the site address file, and this component satisfies those contracts rather than replacing them.
- The organization access token is supplied through the environment variable named in the site configuration. The token is never read from a file and never written to the output tree or the report.
- The site index is written at the root of the output tree and the not-found page is written at the path GitHub Pages serves for unknown addresses.
- One build runs at a time against a given output directory and cache directory. Concurrent builds sharing those directories are out of scope.
- Removing a project from onboarding removes its pages from the next published tree. Retaining historical project sections is not required.
