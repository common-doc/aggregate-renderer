# Contract: consumed publish workflow

**Feature**: `001-aggregate-renderer` | **Plan**: [../plan.md](../plan.md)

The publish workflow of the retired multi-repo setup is reused as-is. This package satisfies the
workflow's contracts and does not replace it, extend it, or acquire a competing workflow. This file
records what the package owes the workflow so that a change to any of it is visibly a change to an
external contract.

## What the workflow owns

- The trigger and schedule.
- The runner image and the PHP setup.
- The step that invokes the build command.
- The upload of `output.directory` as the published artifact.
- The deployment to the Pages environment.

## What this package owes the workflow

| Contract | Requirement | Failure mode if broken |
|---|---|---|
| Configuration file | `site.json` at the workspace root, readable without arguments | Workflow step cannot start the build |
| Command | One entry point, no interactive prompt, no argument required | Workflow step hangs or fails |
| Exit code | `0` complete, `1` defective corpus, `2` blocked before rendering | Workflow cannot branch on outcome |
| Report file | JSON at `output.reportPath` with the schema in [build-report.schema.json](./build-report.schema.json) | Workflow cannot summarize or gate |
| Report exit code | `exitCode` equals the process exit code | Workflow reports a status that contradicts the run |
| Output directory | The whole published tree under `output.directory`, no other path | Deployment publishes nothing |
| Address file | `CNAME` present in the tree before publish | Deployment loses the custom hostname; the build fails first with `missing-cname` |
| Hostname | Supplied by `site.json`, identical to the `CNAME` content | Addresses and hostname disagree |
| Credentials | Read from the environment; never written to the tree or the report | Secret exposure |
| Determinism | Two runs over an unchanged corpus produce identical trees | Every run appears as a full site change |

## Explicitly out of scope

- Choosing the Pages source, the environment, or the protection rules.
- Deciding whether a defective build still deploys. The workflow sees exit code `1` and its existing
  logic applies unchanged.
- Caching the render cache between runs. The cache directory is honoured when present, and its absence
  costs time rather than correctness.
- Any change to triggers, job names, or secrets.

## Acceptance

The contract is satisfied when the build produces, from a clean checkout with only the token present
in the environment:

1. Exit code `0` and a report whose `exitCode` is `0` when the corpus is complete.
2. A tree containing `CNAME`, `index.html`, `404.html` and one file per page address.
3. A report whose totals reconcile with the tree and with the discovered project count.
