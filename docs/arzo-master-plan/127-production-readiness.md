# Production Readiness Review

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P1 · **Phase:** any — runs before each subsystem carries real load
**Depends on:** `128-definition-of-done.md`, `129-quality-gates.md`, `124-security-testing-plan.md`, `125-performance-testing-plan.md`, `126-disaster-recovery-plan.md`
**Blocks:** `130-launch-checklist.md`

---

The gate between "merged" and "carries real load". `128` says what done means; this review checks,
per subsystem, that it is true — with evidence, by someone who did not build it — and records the
decision and every waiver.

## Current state — `MISSING`, 0%

| Fact | State | Evidence |
|---|---|---|
| A review process, template or record | `MISSING` | Search `readiness review`, `production readiness` outside `docs/` → nothing |
| A place to record waivers | `MISSING` | `128` puts waivers "in the feature's work package"; `137` is a scaffold, so there is none |
| An environment to be ready **for** | `MISSING` | ARZO has no staging or production; the deploy pipeline and Vapor app are upstream's (`126`) |
| Mechanical gates | `PARTIAL` | Architecture, cross-tenant, schema and frontend suites exist and pass locally; no CI runs them for ARZO (`129`, ARZ-300) |
| A subsystem close enough to review | `CONFIRMED` | The access engine: `c34f6a59`, `e7228c1d`, plus CRUD and a scan endpoint uncommitted, in progress at audit time |

## Decision: a written review against evidence, not a meeting

The output is a record: for each of `128`'s 24 gates, **met / partial / not met / waived / not
applicable**, with a link to the evidence. A meeting is optional; the record is not.

Three possible decisions:

| Decision | Meaning |
|---|---|
| `READY` | Every applicable gate met |
| `READY_WITH_WAIVERS` | The rest waived under the rules below, each with an expiry |
| `NOT_READY` | Anything else |

## The checklist, derived from `128`

What the reviewer looks at for each gate. "Mechanical" means `129` enforces it and the reviewer checks
the CI result rather than re-deriving it.

| # | Gate | Evidence the reviewer checks | Mechanical? |
|---|---|---|---|
| 1 | Database | Migration read; indexes match the real queries (`EXPLAIN` on the hot path); constraints enforce invariants; `down()` exercised | Partly — migration gate |
| 2 | Backend layering | No Eloquent or query builder above repositories; deliberate exceptions listed | Partly — `LayeringTest` misses the query builder |
| 3 | Business logic | Rules in domain services with isolated unit tests | No |
| 4 | API | Routes in OpenAPI (`scramble:analyze` clean); pagination and filters on collections | Yes |
| 5 | Frontend | Loading, empty, error, partial, offline, permission-denied states — screenshots or E2E | No |
| 6 | Validation | Server rules authoritative; every foreign-key input scoped to the tenant (`124` A3) | No |
| 7 | Authorization | Every route in the generated cross-tenant test; role matrix rows (`124` A1, A5) | Yes |
| 8 | Error handling | Custom exceptions; no 500 on a race or a bad id | No |
| 9 | Edge cases | Concurrency, duplicates, clock skew, time zones, empty and maximum inputs — each named with its test | No |
| 10 | Security | `124` catalogue rows for this subsystem pass; threat model from `64` reviewed | Partly |
| 11 | Auditability | Transitions record who, when, from → to, why; nothing destroys history | No |
| 12 | Testing | Unit, integration, E2E for the real journey; offline and hardware where relevant | Partly |
| 13 | Performance | `125` scenario for this path passed on the production-like environment | Warn only |
| 14 | Accessibility | Keyboard, labels, contrast, screen reader (`81`) | No |
| 15 | Localization | User-facing strings translatable and translated; RTL considered (`82`) | Partly — lint |
| 16 | Documentation | `02` and `136` updated; operator notes where behaviour is non-obvious | Yes — doc-sync gate |
| 17 | Monitoring | Metrics and logs enough to diagnose at 2am (`78`) | No |
| 18 | Deployment | Ships through the ARZO pipeline; migration order safe; feature flag if risky (`123`) | Yes, once the pipeline exists |
| 19 | Operational readiness | Runbook entry; failure modes; recovery procedure **rehearsed** (`126`) | No |
| 20 | Offline | Works offline, or the operator is explicitly told (`71`) | No |
| 21 | No silent data loss | A test per network condition | No |
| 22 | Idempotent under replay | Replay test | No |
| 23 | Non-engineer recovery | Walked through by an operator, timed | No |
| 24 | Real hardware | Field test on the committed hardware family (`37`, `102`) | No |

Gates 20–24 apply to anything used at a live event. Anything touching personal data also needs its
DPIA or privacy note (`65`).

## Decision: the reviewer is not the author, and an AI is not a reviewer

- **The author cannot review.** Nor can anyone who wrote a substantial part of the change.
- **Gates 19–24 are reviewed by the operations lead** (`106`), because they are about people under pressure, not code.
- **The chair is the engineering lead** and signs the decision. If the engineering lead is the author, the chair passes to the product or operations owner.
- **An AI assistant may prepare the evidence; it may not review or decide** (`133`). This repository's recent commits are AI co-authored, which makes the rule concrete rather than hypothetical.
- **With a team of one**, independence is bought: the operations owner reviews gates 5 and 14–24, and security-critical subsystems (access control, tenancy, payments) get an external reviewer. Stating this is better than pretending a self-review is a review.

## Decision: waivers are allowed, named, limited and expiring

| Rule | Why |
|---|---|
| **Gate 7 (cross-tenant negative tests) and gate 21 (no silent data loss) cannot be waived** for anything carrying real client or attendee data | They are the two failures that cannot be apologized away (`128`) |
| Gates 1–19: the engineering lead may waive | |
| Gates 20–24: the operations lead **and** the engineering lead, jointly | The risk lands on site |
| Each waiver records: gate, reason, residual risk, compensating control, **expiry**, owner | An undocumented gap is a defect; a documented one is a decision (`128`) |
| An expired waiver reverts the decision to `NOT_READY` | Waivers otherwise become permanent |
| Commercial pressure may request a waiver, and is written down as the reason when it is the reason. The business owner may **accept** a residual risk in writing; nobody may declare a gate met that is not | The system records the decision; a human makes it |

## Record template

```
Readiness review — <subsystem> — <date>
Scope:        <commits / PRs / feature flags>
Author(s):    <names>        Reviewer(s): <names>        Chair: <name>
Decision:     READY | READY_WITH_WAIVERS | NOT_READY
Gates:        1..24 — MET | PARTIAL | NOT_MET | WAIVED | N/A — evidence link each
Waivers:      gate · reason · residual risk · compensating control · expires · owner
Follow-ups:   item · owner · due
```

Kept in the work package (`137`) for the change, and linked from the launch record (`130`).

## Worked example: the access engine at `e7228c1d`

Scope: `AccessDecisionService`, `GrantMaterializationService`, `CredentialIssuanceService`,
`AccessScanService`, their migrations, and — noted separately — the uncommitted scan endpoint.

| # | Gate | State | Evidence |
|---|---|---|---|
| 1 | Database | PARTIAL | CHECKs for rule effect and exactly-one grant target; unique `client_generated_id`; indexes on credential, zone, event (live `pg_indexes`). `down()` untested; `access_logs` has no `device_id`, which `24` specifies |
| 2 | Layering | PARTIAL | `AccessScanService` and `CredentialIssuanceService` use the query builder directly in the domain layer (`AccessScanService.php:29,48-228`), justified in a docblock, not waived |
| 3 | Business logic | **NOT MET** | Pure decision with 35 tests — but rule subjects are ignored everywhere (`124` ST1): a subject-scoped DENY closes a zone to everyone |
| 4 | API | NOT MET | No committed route; the scan endpoint is uncommitted |
| 5 | Frontend | NOT MET | No UI |
| 6 | Validation | NOT MET | Access point not scoped to the event; `occurred_at` and `direction` trusted (`124` ST2, ST3, ST6) |
| 7 | Authorization | PARTIAL | Scan action authorizes the event; foreign-event tests uncommitted; no per-event roles, so any account member can scan (ARZ-011, ARZ-012) |
| 8 | Error handling | PARTIAL | Concurrent duplicate replay surfaces as a 500 (`125` L3) |
| 9 | Edge cases | PARTIAL | Re-entry, exits, midnight wrap, replay tested. Not: venue time zone — hardcoded `'UTC'` (`AccessScanService.php:83`, ARZ-302); DENY rules never match ROOM or SESSION targets (`AccessDecisionService.php:262-269`); `enforce_capacity` ignored (`:304-309`); concurrent scans |
| 10 | Security | NOT MET | ST1–ST3, ST6 open |
| 11 | Auditability | PARTIAL | Append-only `access_logs` with operator; revocation records who and why (`CredentialIssuanceService.php:60-71`). But re-materializing grants **hard-deletes** the previous ones — `access_grants` has no `deleted_at` (`GrantMaterializationService.php:153-156`) |
| 12 | Testing | PARTIAL | 35 unit + 11 integration + 20 schema; no E2E, no golden vectors (`37`) |
| 13 | Performance | NOT MET | Unmeasured; code reading predicts a miss (`125` L1, ARZ-307) |
| 14 | Accessibility | N/A until UI | — |
| 15 | Localization | NOT MET | Nine `reason` strings returned to scanners are English literals, none wrapped in `__()`. Translating inside a pure function would break purity: return a reason code, translate at the edge |
| 16 | Documentation | PARTIAL | `24` and `136` describe it; `02` still audits `7dec84ca` and does not mention the engine |
| 17 | Monitoring | NOT MET | No logging or metrics in any of the four services |
| 18 | Deployment | NOT MET | No ARZO pipeline or environment; no feature flag (`123`) |
| 19 | Operational readiness | NOT MET | No runbook entry, no rehearsed recovery |
| 20 | Offline | N/A server-side | Device side is Phase 4 by design |
| 21 | No silent data loss | PARTIAL | Idempotent insert; the replay race fails loudly, not silently |
| 22 | Idempotent under replay | MET | `AccessScanServiceTest.php:143`; unique index — with `124` ST4 |
| 23 | Non-engineer recovery | NOT MET | Nothing to recover with yet |
| 24 | Real hardware | NOT MET | No hardware committed (R5) |

**Decision: `NOT_READY`.** One gate met, nine partial, twelve not met, two not applicable. That is the
expected state for a service layer one day old — the review's value is the list, not the verdict.

The shortest path to `READY_WITH_WAIVERS` for an **online-only pilot at one staffed door with a paper
fallback**: fix ST1, ST2, ST3, ST6, L1 and ARZ-302; commit the endpoint with its cross-tenant rows;
pass `125` scenario 1; add a runbook entry and logging; then waive gates 5 and 14 (operators use the
API through a thin internal screen), and 20, 23 and 24 until expiry at the end of that pilot.

## Open questions

- **Who chairs when there is one engineer?** Recommended above: the product or operations owner, with an external reviewer for security-critical subsystems. Needs a name (`106`).
- **Can commercial pressure waive gate 7 or 21?** No. If the business wants that power, it should be an explicit change to this document, not a waiver.
- **Does a readiness decision expire with time?** Leaning yes — re-review after six months or any change to the reviewed paths, whichever is first.
- **External reviewer budget?** `UNVERIFIED` — business.

## Related

`128-definition-of-done.md` · `129-quality-gates.md` · `130-launch-checklist.md` ·
`124-security-testing-plan.md` · `125-performance-testing-plan.md` · `126-disaster-recovery-plan.md` ·
`24-access-control.md` · `37-hardware-integration.md` · `106-event-operating-model.md` ·
`133-ai-capabilities.md` · `137-engineering-work-packages.md` · `65-privacy-gdpr.md`
