# Quickstart: aggregate documentation site rendering

**Feature**: `001-aggregate-renderer` | **Plan**: [plan.md](./plan.md)

Each scenario below is a runnable check for one user story. They are written to be executed by hand
against a real corpus and by the end-to-end integration test against a fixture corpus.

## Prerequisites

- PHP 8.4 or 8.5 with the pinned platform `8.4.99` resolving.
- `composer install` completed, including `common-doc/commondoc`.
- A token available in the environment variable named by `site.json`, for live discovery runs.
- Git available on `PATH`, for live source materialization.

## Minimal configuration

`site.json` at the workspace root:

```json
{
  "organization": {
    "name": "common-doc",
    "tokenEnvVar": "DOCS_TOKEN"
  },
  "output": {
    "directory": "dist",
    "reportPath": "dist/report.json",
    "cacheDirectory": ".cache/multi-repo"
  },
  "site": {
    "hostname": "docs.example.org"
  }
}
```

## Scenario 1: publish one aggregate site (US1)

```sh
bin/aggregate-render site:build
echo $?
```

Expected:

1. Exit code `0`.
2. The command printed one line per project, then a summary of project and page counts.
3. `dist/CNAME` contains `docs.example.org` and a trailing newline.
4. `dist/index.html` links to every project and version.
5. `dist/404.html` exists.
6. Every address printed in the summary resolves to a file under `dist/`.

## Scenario 2: refuse an ambiguous or incomplete site (US2)

Point two fixture projects at the same repository name, then build. Expected: exit code `2`, no page
written, and `dist/report.json` carrying `duplicate-slug` for the colliding entries and zero pages
rendered.

Then remove `dist/CNAME` after a successful build and run the publish precondition check. Expected:
`missing-cname` and a non-zero exit.

Then make one document in one project unparsable, leaving the rest intact. Expected: exit code `1`,
that project `partial`, every other project `ok`, and `totals.pages` counting only the pages that were
written.

Reconcile: `totals.ok + totals.partial + totals.failed + totals.skipped` equals the number of
discovered projects, and `totals.pages` equals the number of page files in the tree.

## Scenario 3: onboard with one repository property (US3)

Set the `doc-target` custom property to `docs` on a repository that carries no version tags and no
version directories, then build. Expected: the project appears in the output and in the report as
`skipped` with `no-versions`, and no other project is affected.

Clear the property and build again. Expected: the project is absent from the output tree and from the
report, and nothing else changes.

## Scenario 4: rebuild incrementally for the identical site (US4)

```sh
rm -rf dist .cache && bin/aggregate-render site:build
cp -r dist /tmp/clean
bin/aggregate-render site:build
diff -r dist /tmp/clean
```

Expected: `diff` reports nothing. The second run reports reused pages and is faster than the first.

Then change one document and build again. Expected: exactly that page and the pages whose navigation
depends on it differ.

Then change the site's document template file and build again. Expected: every page differs, because
the template hash participates in the key.

Then change `site.json` in a way that does not alter output, for example the cache directory. Expected:
the cache is retained, because the cache directory is excluded from the configuration hash.

## Scenario 5: give a site its own document styling (US5)

Add to `site.json`:

```json
{
  "templates": {
    "map": {
      "commondoc::document": "templates/site/document.phtml"
    }
  }
}
```

Build without touching any source file. Expected: every page renders through the overriding template,
and the change is attributable to configuration alone.

Point the map at a file that does not exist. Expected: `invalid-site-config`, exit code `2`, no page
written.

## Parity check

```sh
bin/aggregate-render site:build   # with versions.strategy ref
bin/aggregate-render site:build   # with versions.strategy tree, same corpus
```

Expected: identical output trees and identical report project entries. The unit parity test asserts the
same equality at the seam, without a build.

## Tool gates

```sh
mago format --check
mago lint
mago analyze
mago guard
vendor/bin/phpunit
vendor/bin/infection --min-msi=95 --min-covered-msi=95
```

Expected: every command exits zero with no new baseline entry.
