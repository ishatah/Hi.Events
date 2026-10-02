# Engineering Work Packages

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P1 · **Phase:** every phase
**Depends on:** `136-master-backlog.md`, `128-definition-of-done.md`, `138-acceptance-criteria.md`
**Blocks:** execution of any backlog item

---

## What a work package is

The expansion of one backlog item into something an engineer can execute **without guessing**. If it
needs guessing, it is not ready.

Packages are written when an item is picked up, not all up front. A package written six months
early describes a codebase that no longer exists — this plan has already watched that happen three
times in two days: ARZ-081's approach reversed on reading the waitlist service (`14`), the credential
CHECK landed with two sources where four were planned (`32`), and the Phase 2 schema landed ahead of
Phase 1's RBAC (`115`).

## Where packages live

**In the issue tracker of ARZO's own repository, from this template** — not in this folder.
Documents here and tickets there would drift within a sprint.

That repository does not exist yet (`123` RS1). Until it does, packages have nowhere durable to
live, which is one more reason RS1 is first.

This document keeps the template, the readiness test, and the order in which packages should be
written.

## Template

```
ID · Epic · Priority · Complexity · Owner
Objective            one sentence
Current state        CONFIRMED / PARTIAL / MISSING facts with file:line — re-verified on pickup
Target state         what is true when done
Dependencies         backlog ids; business gates (R1, R5, hardware)
Database             migrations (schema) and data migrations (122) — separately
Backend              actions, handlers, services, repositories touched or added
Frontend             routes, components, states (loading/empty/error/offline/denied)
API                  endpoints, OpenAPI impact, webhook event types (49)
Infrastructure       queues, scheduled jobs, config, env vars
Security             threats (64), authorization (09), tenancy (08), PII (65), audit (67)
Testing              unit, integration, E2E, offline, hardware — mapped to 138 criteria sets
Migration            ordering, backfill, reversibility, rehearsal on production-shaped data
Rollout              flag (123), freeze window, staging soak
Rollback             exact steps; what data survives
Acceptance criteria  numbered, testable — composed from 138 sets
Definition of done   128 gates, each met or waived with a reason
Docs to update       02, 109, 136, 140, 139 and the owning document (00 sync rule)
```

## The readiness test

A package is ready when all of these are true:

| # | Check |
|---|---|
| 1 | Current state re-verified **on the day of pickup**, not copied from the document |
| 2 | Every dependency is `DONE`, or the package states what it stubs and why |
| 3 | Every acceptance criterion is testable by someone other than the author |
| 4 | Rollback is written as steps, not "revert the PR" |
| 5 | Business gates named — a package that needs a printer says so |
| 6 | Waivers of `128` gates are written down with the accepted risk |

## Worked example — ARZ-301

The first package to execute, shown in full so the template is proven against a real item.

```
ARZ-301 · Data · P0 · S · Backend engineer

Objective
  Every non-deleted attendee has a person_id, in every environment.

Current state (CONFIRMED, e7228c1d)
  database/migrations/2026_09_29_000008_backfill_venues_and_persons.php:163-206 pages with
  chunk(500) over attendees WHERE person_id IS NULL while setting person_id in the loop.
  OFFSET paging over a shrinking set skips alternate batches. Ran only on an empty dev DB.

Target state
  Fixed migration for environments that have not run it; an idempotent command for those
  that have; an assertion of zero nulls.

Dependencies   none

Database       edit 000008 to chunkById(500, fn, 'attendees.id', 'id'); no schema change
Backend        console command persons:backfill — same logic, chunkById, --dry-run prints
               the count it would change, exits non-zero if nulls remain
Frontend       none
API            none
Infrastructure none
Security       persons is PII; the command logs counts, never names or emails
Testing        Feature test seeding 1,250 attendees across 2 accounts (crosses two batch
               boundaries): after the command, 0 null person_id; same-email attendees in one
               account share a person; different accounts never share one; a second run
               changes nothing (idempotent)
Migration      run the command in every environment that already ran 000008
Rollout        no flag; outside any event freeze
Rollback       the command only fills nulls; to undo, null person_id for ids logged by the run
Acceptance     138 set "Data migration" items 1–6
Done           128 gates 1, 3, 6, 9, 12, 16, 18, 19; gates 5, 14, 15 not applicable
Docs           122 D1 → DONE; 16 correct "fully backfilled"; 136 ARZ-301 → DONE
```

## The first packages to write

In this order. The first eight are small, independent, and reduce present risk.

| Order | Item | Why first |
|---|---|---|
| 1 | ARZ-300 ARZO repository, CI, branch protection (`123`) | Nothing else is safe, backed up, or gated without it |
| 2 | ARZ-301 `persons` backfill fix (`122`) | Silent data loss on first real deploy |
| 3 | ARZ-305 Messaging test-send and tier defects (`42`) | Live: a test send emails real customers |
| 4 | ARZ-320 Rules honour their subject, with ARZ-302 venue timezone (`124`, `115`) | The rule CRUD and scan routes are being built now; both defects must be fixed before they merge |
| 5 | ARZ-321 Scan endpoint trust (`94`) | Same reason — cross-tenant writes through an unchecked access point |
| 6 | ARZ-317 Licence and attribution (`98`) | A compliance defect in the working tree, and a business decision to take |
| 7 | ARZ-323 Anonymize the new tables (`108`) and ARZ-303 encrypt ID fields (`65`) | Before any environment holds real people's data in `persons` |
| 8 | ARZ-304 Webhook retries (`49`) | Integrators silently lose events |
| 9 | ARZ-013, ARZ-011, ARZ-012 Tenant scope, RBAC, per-event roles (`08`, `09`) | Gate for every multi-party feature; the riskiest refactor |
| 10 | ARZ-041 Check-in consolidation (`18`) | F10; the dual-write safety property for Phase 2 |

Items 9 and 10 are large; each becomes several packages, one per sequence step in `114` and `18`.
Items 1–8 are small — roughly two to three weeks of one engineer in total.

## Open questions

- **Which tracker?** GitHub Issues in the ARZO repository is the lowest-friction choice and keeps packages next to the code and CI.
- **Who reviews a package for readiness** before work starts? Someone other than the author, as `127` requires for production readiness.
- **Estimation** — the backlog uses S/M/L/XL deliberately (`136`). Keep that; do not convert to hours.

## Related

`136-master-backlog.md` · `138-acceptance-criteria.md` · `128-definition-of-done.md` ·
`139-test-matrix.md` · `122-migration-plan.md` · `123-release-strategy.md` · `127-production-readiness.md`
