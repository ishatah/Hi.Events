# Phase 2 — Accreditation, Badges, Access Control

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d` · **Complexity:** L
**Classification:** Derived (from `113`, `136`) · **Prerequisite:** Phase 1 complete — space, time, persons, RBAC, `access_logs`
**Depends on:** `114-phase-1.md`, `23-accreditation.md`, `24-access-control.md`, `21-badge-management.md`, `22-badge-design-printing.md`
**Blocks:** `117-phase-4.md`

---

## Objective

The first phase that delivers visible new capability to an event operator. At the end of it, ARZO can
accredit a person, issue a photo badge, and enforce zone-and-time access rules **online**.

Offline enforcement is Phase 4. This phase assumes connectivity — a deliberate choice to get the
domain right before adding distributed-systems complexity.

## Where it stands — started early, schema complete, behaviour a third done

Phase 2 began before Phase 1 closed: the accreditation, access and badge **schema** and the access
engine's core services landed while ARZ-011/012/013 (RBAC, per-event roles, tenant scope) and
ARZ-041 (check-in consolidation) remain open. That ordering is tolerable only because nothing new is
exposed to users yet; it becomes a problem the moment accreditation review ships (see prerequisites).

| Item | State at `e7228c1d` | Evidence |
|---|---|---|
| ARZ-050 Accreditation types + type rules | **Schema done**; type CRUD uncommitted, in progress | `2026_09_30_000001`; working tree |
| ARZ-051 Applications + approval workflow | **Not started** — table only | No handler, no state machine |
| ARZ-052 Credentials + one-of CHECK | **Done** — schema, plus `CredentialIssuanceService` (40-char random identifier, SHA-256 hash) | `e7228c1d` |
| ARZ-053 Backfill credentials for existing attendees | **Not started** | — |
| ARZ-060 Access rules engine | **Core exists** — `AccessDecisionService` pure, 35 table-driven tests; `AccessScanService` with 11 DB tests. **Rules ignore `subject_type`/`subject_id`**: a DENY aimed at one badge matches everyone at that point (ARZ-320, `124` ST1) | `c34f6a59`, `e7228c1d` |
| ARZ-061 Grant materialization | **Partial** — `GrantMaterializationService` honours `approved_zones`, but copies **every** event-level ALLOW rule onto **every** credential, whatever the rule's subject (ARZ-320) | `e7228c1d` |
| ARZ-062 Anti-passback + re-entry | **Done in the decision function**; exits always granted | `AccessDecisionServiceTest` |
| ARZ-063 Occupancy | Derived per scan in `AccessScanService`; **no snapshot job** | `52` |
| ARZ-064 Rule simulator | **Not started** — the pure function makes it cheap | — |
| ARZ-070–073 Badge templates, render, print jobs, reprint | **Schema only** (`badge_templates`, `badges`, `badge_print_jobs`) | `c34f6a59` |
| ARZ-074 Photo capture | Not started | — |
| ARZ-041 Check-in consolidation | **Not started** — F10 still open (`49`) | — |

## Prerequisites that are now hard

Discovered by the 31–60 and 61–140 audits. Each blocks a specific item, not the whole phase.

| Prerequisite | Blocks | Why |
|---|---|---|
| **ARZ-011/012 RBAC and per-event roles** | ARZ-051 review UI | An accreditation officer must approve `MEDIA` without seeing the account's orders. Today any account user sees everything (`09`, `32`). |
| **Encrypt `persons` ID fields** (`65`) | Any accreditation type with `requires_id_document` | `id_document_number` and `date_of_birth` are plaintext `text` columns; the model has no casts. Collecting a passport number into them would be a reportable exposure. |
| **Fix the `persons` backfill** (`122`) | ARZ-053, and every environment with data | The backfill pages with `chunk()` over the column it updates and skips alternate batches. |
| **Credential identifier format** (`38`) | ARZ-070 first badge print | Add a versioned prefix and upper-case the token **before any credential is printed** — afterwards the format is permanent for that event. |
| **Audit trail** (`67`) | ARZ-051, credential revoke, access override, badge void | These are contestable decisions; `event_logs` has never been written and `order_audit_logs` has no actor column. |
| **AGPL / licence position** (R1, `66`) | The phase as a whole | This is where proprietary work starts to accumulate. |

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-050 Accreditation types + type rules | `23` | New |
| ARZ-051 Applications + approval workflow | `23` | New |
| ARZ-052 Credentials + one-of CHECK | `23` | New — done |
| ARZ-053 Backfill credentials for existing attendees | `23`, `122` | Migration |
| ARZ-060 Access rules engine | `24` | New — core done |
| ARZ-061 Grant materialization | `24` | New — done |
| ARZ-062 Anti-passback + re-entry | `24` | New — done in the function |
| ARZ-063 Derived occupancy + snapshot job | `24`, `52` | New |
| ARZ-064 Rule simulator | `24` | New |
| ARZ-070 Badge templates — **presets first**, canvas deferred (`22`) | `21`, `22` | New |
| ARZ-071 Server-side render, raster-capable, Arabic-tested (`39`) | `22` | Replace |
| ARZ-072 Print jobs as retryable entities | `21`, `39` | New |
| ARZ-073 Reprint, void, history | `21` | New |
| ARZ-074 Photo capture + normalization (`73`) | `23` | New |
| ARZ-041 Check-in dual-write cutover | `18` | Refactor |
| Staff and exhibitor-staff passes **as accreditation types** (`32`, `57`) | `23` | Decision, no new table |

## Sequence

```
1. Encrypt persons ID fields; fix the persons backfill            (prerequisites)
2. ARZ-041 dual-write: both check-in paths also write access_logs  (closes F10)
3. ARZ-053 credential backfill — batched with chunkById, idempotent
4. ARZ-064 simulator on the pure function                         (before any rule UI)
5. ARZ-050 type CRUD + rules UI, behind the simulator
6. ARZ-051 application + review workflow, with audit              (needs ARZ-011/012)
7. ARZ-071/072 render + print jobs; ARZ-070 presets; ARZ-073
8. ARZ-074 photo capture
9. ARZ-063 snapshot job
```

Step 4 before step 5 is deliberate: organizers will write contradictory rule sets (`24`), and the
simulator is how they find out before the doors do.

## Exit criteria

| # | Criterion | Verified by |
|---|---|---|
| 1 | An application is submitted, reviewed, approved and rejected, each transition in the audit trail with actor and reason | Feature test + audit assertion |
| 2 | An `ACCREDITATION_OFFICER` scoped to event A can approve `MEDIA` on A and cannot see event B | Per-event role test |
| 3 | An approved application issues a credential whose grants materialize in < 500 ms p95 | Timed integration test |
| 4 | A credential is refused at a zone it has no grant for, with `DENIED_NO_GRANT` logged | Scan-service test — **met** |
| 5 | A credential is refused outside its time window, in the **venue's** timezone | Test with a non-UTC venue |
| 6 | Enter, exit, re-enter produces three `access_logs` rows | Scan-service test — **met** |
| 7 | Anti-passback refuses a second entry with no exit when `allow_reentry` is false | Decision test — **met** |
| 8 | Both check-in paths write `access_logs` and emit `checkin.created` | Dual-path test + webhook assertion |
| 9 | Every existing non-cancelled attendee has an `ACTIVE` credential | Backfill assertion, zero missing |
| 10 | A badge renders at exact mm dimensions in < 2 s p95, **including an Arabic name that shapes correctly** | Render test with fixture names |
| 11 | A reprint reproduces the original template version | Test |
| 12 | A voided badge leaves its credential active; a revoked credential leaves its badge record intact | Test |
| 13 | The simulator answers 20 table-driven "would this credential get in here, now?" cases correctly | Test |
| 14 | ID document fields are unreadable in the database without the application key | DB assertion |
| 15 | Disabling the new path restores the old check-in behaviour exactly | Rollback drill |

Criterion 5 exists because of a live defect at `e7228c1d`: `AccessScanService` passes
`venueTimezone: 'UTC'` (`AccessScanService.php:83`) and `AccessDecisionService` never reads it,
formatting the scan time as-is (`AccessDecisionService.php:226`). A 08:00–22:00 window at a Doha
venue therefore evaluates as 11:00–01:00 local. The fix is small — convert `occurredAt` to the
venue's timezone before comparing wall-clock windows — and belongs before any rule UI ships (`24`,
`25` stores `venues.timezone` for exactly this).

## Non-engineering gates

Not deliverable on software alone:

- **Badge printers, spares and stock** (`102`) — for criterion 10's physical half and for any pilot
- **Licence position resolved** (R1, `66`)
- **A real venue and a real past event modelled** in the new schema before the rule UI is built (`114`'s last risk, still open)

## Out of scope

- Offline enforcement, device keys, print hosts, RFID/NFC (Phase 4)
- The free-form badge canvas — presets only (`22`)
- Exhibitor and staff *workflows* — their passes are in scope as accreditation types; rosters and portals are Phases 3 and 5
- Accreditation triage by AI (`133` A3) — optional, after criterion 1, never deciding

## Risks

| Risk | Mitigation |
|---|---|
| Schema ran ahead of RBAC — review UI tempts shipping on account-wide access | Criterion 2 is an exit gate, not a follow-up |
| Credential backfill is large | `chunkById`, idempotent, restartable, run off-peak (`122`) |
| Rule sets contradict each other | Simulator first (sequence step 4) |
| Arabic badge rendering fails late | Criterion 10 tests it in the render pipeline, before hardware |
| No printers to test against | Waive `128` gate 24 in writing until `102` delivers |

## Related

`113-roadmap.md` · `114-phase-1.md` · `116-phase-3.md` · `117-phase-4.md` · `23-accreditation.md` ·
`24-access-control.md` · `21-badge-management.md` · `22-badge-design-printing.md` ·
`38-scanner-platform.md` · `65-privacy-gdpr.md` · `67-audit-logging.md` · `122-migration-plan.md` ·
`136-master-backlog.md` · `128-definition-of-done.md` · `120-risk-register.md`
