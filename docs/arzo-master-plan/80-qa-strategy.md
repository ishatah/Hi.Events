# QA Strategy

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P0 for the missing CI gate (`123` RS1–RS3); P1 otherwise · **Phase:** now; field testing from Phase 2
**Depends on:** `79-testing-strategy.md`, `123-release-strategy.md`, `128-definition-of-done.md`
**Blocks:** `129-quality-gates.md`, `127-production-readiness.md`, `131-event-readiness-checklist.md`

---

`79` says what to test. This document says who tests, when, against what, and who signs. The short
version: the automated suites are good, nothing runs them for ARZO, and there is no human QA function.

## Current state — `PARTIAL`, ~40%

### The suites — `CONFIRMED`

| Fact | Evidence |
|---|---|
| Backend: 208 Unit and 36 Feature test files; ~1,259 unit tests | `git ls-tree e7228c1d backend/tests`; audit figure |
| `tests.yml` runs Unit then Feature on PHP 8.3, 8.4 and 8.5 against a Postgres 17 service | `.github/workflows/tests.yml:20,27,91,111` |
| Frontend: Vitest, **2 files, 24 tests**, `environment: "node"`, `*.test.ts` only — no component can be rendered | `frontend/vitest.config.ts:5-6`; `themeUtils.test.ts` (18), `ssrRequestContext.test.ts` (6) |
| Lint and typecheck run as **ratchets**: fail only if errors exceed 768 and 46 | `frontend-tests.yml:51,68` |
| E2E: 73 specs, 27 `@smoke`; PRs run smoke only unless labelled `full-e2e`; pushes and a 03:00 UTC nightly run the full suite in 2 shards | `e2e.yml:21,35-41` |
| E2E retries twice in CI and runs **desktop Chromium only** | `e2e/playwright.config.ts:14,30-35` |
| E2E runs every job **synchronously** and has no scheduler | `docker/e2e/.env:28` (`QUEUE_CONNECTION=sync`); no worker or scheduler service in `docker-compose.e2e.yml` |
| Check-in: one spec drives the scanner app; two cover list management | `e2e/tests/check-in/check-in-app.spec.ts`; `tests/management/check-in-lists*.spec.ts` |
| No accessibility automation — no axe, no `jsx-a11y` | `frontend/package.json`, `e2e/package.json`, `.eslintrc.cjs:3-19` |
| Pint is documented but not in CI | `CLAUDE.md`; absent from `tests.yml` |

### The process — `CONFIRMED` where stated

| Fact | Evidence |
|---|---|
| **None of the above runs for ARZO.** The only remote is upstream, ARZO cannot push to it, and all 14 ARZO commits are local | `git remote -v`; `gh api` → `push: false`; `git status -sb` → `ahead 14` (`123`) |
| All 14 ARZO commits have **one author** | `git log origin/develop..e7228c1d --format=%an` |
| No CODEOWNERS; the PR template and CLA workflow are upstream's | `.github/` |
| A dedicated QA role, a second reviewer, or an acceptance step | `UNVERIFIED` — none is evidenced; review may happen out of band |

The Phase 0 "CI gates" (ARZ-004 to ARZ-007) are therefore **conventions**, honoured when someone
runs them locally. `113`'s Phase 0 exit criterion — CI gates architecture and tenancy invariants — is
not yet true.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| Q1 | No CI runs ARZO's code; every gate is optional | Above; `123` | **High** — fix now via `123` RS1–RS3 |
| Q2 | E2E exercises queued work synchronously: retries, backoff, `failed_jobs` and `afterCommit` ordering never run before production. `49` W1 (webhook retries never happen) is the class of bug this hides | `docker/e2e/.env:28` | Medium — fix with `83` D3 |
| Q3 | E2E is desktop Chromium only; scanners and kiosks will run on tablets, and iPad means WebKit | `playwright.config.ts:30-35` | Medium |
| Q4 | No scheduler in E2E, so scheduled sends and waitlist expiry are never exercised end to end | Compose file | Low |
| Q5 | Frontend tests cannot render a component (`node` environment, `.test.ts` only) | `vitest.config.ts:5-6` | Low — deliberate per `79`, but the check-in state machine must be extracted into pure functions to be testable |

## Decision: engineer-owned QA with independent acceptance — not a QA department

A separate QA team that tests after development creates a hand-off queue and a second owner for
quality. At ARZO's size (team size `UNVERIFIED`) the engineer who builds a feature writes its tests.
What the author cannot do is **accept** their own work for use at an event.

| Layer | Owner | Evidence it leaves |
|---|---|---|
| Automated gates | The author; enforced by CI | Green required checks (`129`) |
| Code review | A second engineer where one exists | Approved PR |
| Acceptance | **The person who will use it** — ARZO's operations lead for on-site features, a named organizer user for admin features | Acceptance criteria ticked (`138`) |
| Field test | Operations lead with the engineer present | Field-test record (below) |

**Objection:** with one engineer there is no second reviewer. Then acceptance by operations and the
field test carry more weight, and review can be bought in periodically. What must not happen is a
feature reaching a door with only its author's word that it works.

## Decision: field testing is a scheduled activity with a record, not a demo

`128` gate 24 requires real hardware. A demo proves the happy path; a field test is designed to
break things. Every on-site feature gets one before its first event, on the real devices, printers
and network kit (`102`, `103`).

**Standard charters** — each is a scripted attempt to cause a failure:

| Charter | What it tries to break |
|---|---|
| Network pull | Unplug the uplink mid-queue; scans must queue, not vanish (`128` gate 21) |
| Network flap | Toggle every 20 s for five minutes; no duplicates on reconcile |
| Arabic keyboard | Scan with the host OS on an Arabic layout (`38` S1) |
| Printer faults | Paper out, head open, USB pull mid-job; nothing lost, no silent `SENT` (`39`) |
| Clock skew | Device clock set ten minutes wrong; skew detected and flagged (`71`) |
| Battery death | Kill a device with unsynced scans; they replay on boot |
| Two-device capacity | Two devices admit the last place in a capacity-1 zone; flagged, not silently corrected |
| Operator error | Wrong access point configured; the denial pattern makes it visible (`53`) |
| Recovery by a non-engineer | A staff member follows the runbook (`107`) unaided (`128` gate 23) |

The **field-test record** — build, devices, charters run, pass or fail, defects raised — is stored
as evidence and linked from the readiness check "offline drill run" (`59`).

## Who signs what

| Decision | Signed by | Recorded in |
|---|---|---|
| PR merge | Required checks plus a reviewer who is not the author, where one exists | Repository |
| Production release | Engineering lead | Release notes (`123`) |
| On-site feature fit for events | Operations lead, after a passing field test | Field-test record |
| **Event go / no-go** | **The event director** — the system records, it does not decide (`59`, `106`) | `readiness_reviews` |

QA does not sign event readiness. It supplies evidence to the person who does.

## Defect severity

| Severity | Rule |
|---|---|
| **Blocker** | Any silent data loss on an on-site path, regardless of frequency (`128` gate 21); any cross-tenant read |
| High | A door, desk or checkout cannot complete its job and the operator is not told |
| Medium | Degraded with a visible workaround |
| Low | Cosmetic, or a workaround the operator already knows |

A Blocker found inside an event freeze (`123`) goes to the emergency-deploy path; everything else
waits for the freeze to end.

## The event build

Each event runs on a **pinned build** — web release and device app versions — fixed at the T-24h
review (`59`). The regression pack (E2E smoke plus the field-test charters relevant to the event's
features) runs against exactly that build. A fix after the pin means re-running the pack, which is
why fixes during a freeze are rare.

## Flaky tests

A test that fails intermittently is **quarantined with an owner and an expiry date**, never silently
retried into green. Two CI retries (`playwright.config.ts:14`) already hide some flakes; the E2E
summary should report retried-then-passed tests so they are visible. `79` asks who owns E2E
maintenance; the answer is the author of the area the spec covers.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | ARZO repository with required checks (`123` RS1–RS3) | Now |
| 2 | Pint `--test` as a required check | With step 1 |
| 3 | Redis queue, worker and scheduler in the E2E stack (`83` D3); fix specs that assumed synchronous jobs | Phase 1 |
| 4 | A Playwright project on a tablet viewport and WebKit, running the check-in and checkout smoke specs | Phase 1 |
| 5 | Accessibility smoke with axe (`81`) | Phase 1 |
| 6 | Field-test record template and the charters above; first run at the Phase 2 badge desk | Phase 2 |
| 7 | Sign-off matrix adopted into `106` and `127` | Before the first ARZO event on the platform |

## Open questions

- **Is there a second engineer?** If not, budget an external code review per phase — the cross-tenant and access-decision code most of all.
- **Who is the operations lead who accepts on-site features?** A named person, not a role (`106`).
- **Field-test rig** — a permanent set of one of each supported device and printer at base, or borrowed from event stock? Permanent is recommended; it needs `102`.
- **Crowd or usability testing** for attendee surfaces, in Arabic, before the first public event? Worth one session if `82` confirms Arabic.

## Related

`79-testing-strategy.md` · `123-release-strategy.md` · `128-definition-of-done.md` ·
`129-quality-gates.md` · `138-acceptance-criteria.md` · `139-test-matrix.md` · `59-event-readiness.md` ·
`83-devops.md` · `81-accessibility.md` · `102-hardware-procurement.md` · `106-event-operating-model.md` ·
`107-event-day-runbook.md`
