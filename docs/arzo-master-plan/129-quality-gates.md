# Quality Gates

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P0 (ARZ-300 — the ARZO remote); P1 for the rest · **Phase:** 0, reopened
**Depends on:** `79-testing-strategy.md`, `80-qa-strategy.md`, `128-definition-of-done.md`, `00-master-index.md`
**Blocks:** `127-production-readiness.md`, `130-launch-checklist.md`

---

Making the standards mechanical. A gate that is not enforced is not a gate, and a gate that can be
skipped without a recorded reason is not a gate either.

## Current state — `MISSING` in CI, `PARTIAL` on disk, ~15%

**GitHub Actions has never run ARZO's code.** The workflows exist in the working tree; nothing
executes them for ARZO.

| Fact | State | Evidence |
|---|---|---|
| ARZO has no repository of its own | `CONFIRMED` | `git remote -v` → one remote, `origin https://github.com/HiEventsDev/Hi.Events.git` — the public upstream; ARZO cannot push there (coordinator-verified; not re-checked here) |
| ARZO's commits exist only on this workstation | `CONFIRMED` | `git status -sb` → `develop...origin/develop [ahead 14]` |
| Branch protection for ARZO | `MISSING` | There is no ARZO repository to protect. Upstream's settings are not in the repo — `UNVERIFIED` and irrelevant |
| Phase 0 gates (ARZ-004–007) | `PARTIAL` | Exist and pass when run by hand — `tests/Unit/Architecture` on the working tree at audit time: 6 tests OK. `113`'s Phase 0 exit ("CI gates architecture and tenancy invariants") is not met |
| `frontend-tests.yml` | `PARTIAL` | Added by ARZO in `a918015f`; not in upstream (`git ls-tree origin/develop .github/workflows` lists five); has never run anywhere |
| Deploy gated on tests | `MISSING` | `deploy.yml:34-39` — the backend job has no `needs:`; it deploys on every push to `main`/`develop` whatever the tests say. It is upstream's SaaS deploy (Vapor, `s3://hi.events-env-secrets`, `:97-101`) |
| Master-plan doc sync (`00`) | `MISSING` | No check. Drift already present: `02` still audits `7dec84ca` and does not mention the access engine landed in `c34f6a59`/`e7228c1d` |

The workflows as they would behave in an ARZO repository:

| Workflow | Checks | Fate in an ARZO repo |
|---|---|---|
| `tests.yml` | Unit then Feature suites on PHP 8.3/8.4/8.5 with Postgres 17 (`:18-108`) — architecture, cross-tenant, schema and OpenAPI tests included. No `pint`, no dependency audit | **Keep**; make required |
| `frontend-tests.yml` | vitest; ESLint count ≤ 768 (`:46-60`); tsc errors ≤ 46 (`:62-78`). No build | **Keep**; make required |
| `e2e.yml` | `@smoke` (27) on PRs, full sharded suite on push, nightly and the `full-e2e` label (`:27-41`); builds both images, so it also catches build breaks. Needs Stripe test keys (`:59-60`) | **Keep**; smoke required |
| `cla.yml` | Upstream's CLA assistant against `HiEventsDev/cla-signatures` (`:18-30`) | **Delete** — not ARZO's agreement |
| `deploy.yml` | Upstream's Vapor + DigitalOcean deploy | **Delete** until ARZO hosting exists (`84`, `85`); then rewrite gated on green checks |
| `post-release-push-images.yml` | Pushes to `daveearley/*` on Docker Hub (`:37,59,78`) | **Delete** or repoint to an ARZO registry |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| Q1 | **No ARZO remote, CI or branch protection** — every gate in this document is advisory | Above | **Critical**, live — fix now (ARZ-300) |
| Q2 | Deploy is not gated on tests | `deploy.yml:34-39,121-124` | High, the day ARZO copies it |
| Q3 | `LayeringTest` sees only `use HiEvents\Models\`; query-builder access in domain services passes | `LayeringTest.php:107`; `AccessScanService.php:29` | Medium |
| Q4 | `ActionAuthorizationTest` proves a call exists, not that it covers the entity read | `ActionAuthorizationTest.php:177-180` (`124` ST8) | Medium |
| Q5 | No dependency audit, secret scanning or SAST | `124` | Medium |
| Q6 | `pint --test` is required by `CLAUDE.md` and run by no workflow | `tests.yml:86-108` | Low |
| Q7 | Dependabot covers `backend` composer and `frontend` npm only — not `e2e/`, GitHub Actions or Docker base images | `.github/dependabot.yml:1-11` | Low |
| Q8 | Only one migration test exercises `down()`; nothing tests rollback of new migrations | `AddQuantityAppliesToToProductPricesTest.php:41,69` | Medium — see the migration gate |

## Decision: the first gate is an ARZO-owned private remote

Before any other line here means anything:

1. Create a **private** repository under an **organization** ARZO owns, not a person's account.
2. Push `develop`, `main` and tags. Rename the upstream remote to `upstream` and merge from it deliberately.
3. Delete `cla.yml`, `deploy.yml` and `post-release-push-images.yml` (table above). Add the Stripe test keys `e2e.yml` needs.
4. Protect `develop` and `main`: pull request required; required status checks (below); **administrators included**; no force-push; no deletion.
5. Push the uncommitted work in progress to a branch the same day — it is the most exposed code in the repository (`126` D0).

## Decision: block what is deterministic, warn what is noisy

A check blocks when it is fast, deterministic and a failure means a real regression. It warns when it
is environment-dependent. Every warning has a named owner who reads it, or it is deleted. Overrides
happen only through a PR label whose reason is written in the description; label use is reviewed
monthly.

**Ratchet, don't wait for clean.** `frontend-tests.yml` gates ESLint and tsc on a count that may fall
but not rise, and `LayeringTest` pins 11 legacy files. That is the right pattern for adopting a gate
on a dirty codebase, and Larastan and the bundle budget use it too.

## The gates

| # | Gate | Enforced by | Mode | Today (ARZO) |
|---|---|---|---|---|
| G1 | ARZO remote, protected branches | GitHub settings | Prerequisite | `MISSING` |
| G2 | Backend Unit, incl. architecture tests (ARZ-004, ARZ-005) | `tests.yml` | Block | Local only |
| G3 | Backend Feature, incl. cross-tenant (ARZ-006), schema, OpenAPI | `tests.yml` | Block | Local only |
| G4 | Every new route in the cross-tenant test | Generated route test (`124` A1) | Block | Hand list of 20 cases |
| G5 | Frontend unit, lint count, tsc count (ARZ-007) | `frontend-tests.yml` | Block | Local only |
| G6 | E2E smoke on PR; full suite nightly | `e2e.yml` | Block smoke; full failure opens an issue | Local only |
| G7 | Code style | `pint --test` step in `tests.yml` | Block | `MISSING` |
| G8 | Golden-vector parity (`37`) | One JSON vector file run by the PHP suite and by every device implementation | Block | `MISSING` — see below |
| G9 | Migration safety | Script + rollback test (below) | Block | `MISSING` |
| G10 | Dependency audit | `composer audit`; `yarn audit --groups dependencies` | Block high/critical in production deps; warn otherwise | `MISSING` |
| G11 | Secret scanning | gitleaks on the PR diff, with `arzo_`/`arzod_` rules | Block | `MISSING` |
| G12 | Static analysis | Larastan, baseline-gated | Block new findings | `MISSING` |
| G13 | Bundle budget (`74`) | Build job compares gzip size of the entry and the largest route chunks with a committed budget file | Warn until a baseline is set, then block | `MISSING`; `vite-bundle-visualizer` is installed (`frontend/package.json:108`) but nothing measures |
| G14 | Latency budget (`74`, `125`) | Nightly k6 scenarios 1 and 3 against the performance environment | Warn; a failure blocks release into an event freeze window | `MISSING` — no environment |
| G15 | Master-plan doc sync (`00`) | Path check (below) | Block, label override | `MISSING` |
| G16 | No secret behind a `VITE_` prefix (R12) | Script over the env contract | Block | `MISSING` |
| G17 | Deploy only from green `main` | Deploy job `needs:` + protected environment | Block | N/A until ARZO hosting |
| G18 | Readiness decision before real load (`127`) | Human review, linked from the release | Block the release, not the merge | `MISSING` |

**Required status checks** once G1 exists: G2 and G3 on **one** PHP version — the production one;
the Dockerfile uses 8.5 (`backend/Dockerfile:1`) — with the other two nightly; G5; G6 smoke; G7;
G10; G11; G15. Three PHP versions on every PR buys nothing for an application that runs one.

## Golden-vector parity (G8)

`37` requires every implementation of the access decision to agree on a language-neutral file. That
file does not exist, and cannot be exported as it stands: `AccessDecisionServiceTest` is 35 methods
with shared builders, not a data table. Order matters:

1. Fix rule subject scoping first (`124` ST1). Vectors generated today would freeze a defect.
2. Move the cases into `access-decision.vectors.json` — context in, `{result, matched_grant_id, matched_rule_id}` out — read by a PHPUnit data provider.
3. The PHP suite fails if the file and the implementation disagree. From the first device build (ARZ-101, ARZ-152), each device repository runs the same file, pinned by hash.

## Migration safety (G9)

- A PR adding a migration that contains `drop`, `dropColumn`, `renameColumn`, `->change()` or raw `ALTER … TYPE` must carry the `destructive-migration` label **and** a "Rollback" section: data preserved how, restore point, and whether `down()` is safe.
- CI runs every migration added by the PR up, down and up again on a scratch database — the step the `DatabaseTransactions` suite cannot do.
- Destructive migrations are never deployed inside an event freeze window (`123`), and only after a pre-deploy snapshot (`126` D3).

## Master-plan doc sync (G15)

`00` asks every merged feature to update six documents in the same PR. **Recommend a CI path check,
not a PR-template checkbox.** A checkbox is ticked by habit; a path check at least proves the file
was opened.

- **Rule:** a PR that adds or changes a file under `backend/database/migrations/`, adds a file under `backend/app/Http/Actions/`, or adds a route to `backend/routes/api.php` must also modify `docs/arzo-master-plan/02-current-state-audit.md` or `136-master-backlog.md`.
- **Override:** the label `docs-not-affected` with a one-line reason. Visible, countable, reviewable.
- **Only the two authoritative documents are enforced per PR.** `109`, `139` and `140` are derived; forcing them on every PR produces churn edits nobody reads. They are reconciled at each phase boundary as a phase-exit item. This deliberately narrows `00`'s rule.
- **What it cannot do:** prove the edit is correct. Review does that. The check only makes forgetting impossible.

```
# sketch — docs-sync job
changed=$(git diff --name-only origin/develop...HEAD)
needs_docs=$(echo "$changed" | grep -E '^backend/database/migrations/|^backend/app/Http/Actions/|^backend/routes/api.php$')
has_docs=$(echo "$changed" | grep -E '^docs/arzo-master-plan/(02-current-state-audit|136-master-backlog)\.md$')
[ -z "$needs_docs" ] || [ -n "$has_docs" ] || label_present docs-not-affected || exit 1
```

## Migration

| Step | Change | When |
|---|---|---|
| 1 | G1: private remote, push, delete upstream-only workflows, protect branches | Today (ARZ-300) |
| 2 | Required checks G2, G3, G5, G6 | Same day |
| 3 | G7 `pint`, G10 audits, G11 gitleaks, G16 env contract | First week |
| 4 | G15 doc-sync check | First week |
| 5 | G9 migration safety | Before the next destructive migration |
| 6 | G4 generated cross-tenant test | Before the uncommitted CRUD merges |
| 7 | G12 Larastan baseline; G13 bundle baseline | Next |
| 8 | G8 golden vectors | After `124` ST1; before ARZ-101 |
| 9 | G14 latency budget; G17 deploy gating | With the performance environment and hosting |

## Open questions

- **Who owns the ARZO GitHub organization and its billing?** Business. Whether required reviewers, secret-scanning push protection and CodeQL are available on private repositories depends on the plan — `UNVERIFIED`.
- **Required human review with one engineer?** A required approval would block all work. Recommend required status checks now, and required review on `main` only, from a second person, once there is one.
- **How are upstream merges gated?** They are large, mechanical and touch everything. Recommend one PR per upstream sync, full E2E via the `full-e2e` label, and the doc-sync override label with "upstream sync" as the reason.

## Related

`00-master-index.md` · `79-testing-strategy.md` · `80-qa-strategy.md` · `128-definition-of-done.md` ·
`124-security-testing-plan.md` · `125-performance-testing-plan.md` · `126-disaster-recovery-plan.md` ·
`127-production-readiness.md` · `37-hardware-integration.md` · `74-performance.md` · `83-devops.md` ·
`123-release-strategy.md` · `113-roadmap.md` · `136-master-backlog.md`
