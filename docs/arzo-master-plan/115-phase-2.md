# Phase 115 — Accreditation, Badges, Access Control

**Status:** SCAFFOLD · **Audit date:** 2026-09-28 · **Complexity:** L
**Prerequisite:** Phase 1 complete — space, time, persons, RBAC, `access_logs`

---

## Objective

The first phase that delivers visible new capability to an event operator. At the end of it, ARZO can accredit a person, issue a photo badge, and enforce zone-and-time access rules online.

Offline enforcement is explicitly Phase 4. This phase assumes connectivity, which is a deliberate scoping choice to get the domain right before adding distributed-systems complexity.

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-050 Accreditation types + type rules | `23` | New |
| ARZ-051 Applications + approval workflow | `23` | New |
| ARZ-052 Credentials + one-of CHECK constraint | `23` | New |
| ARZ-053 Backfill credentials for existing attendees | `23` | Migration |
| ARZ-060 Access rules engine + priority evaluation | `24` | New |
| ARZ-061 Grant materialization | `24` | New |
| ARZ-062 Anti-passback + re-entry rules | `24` | New |
| ARZ-063 Derived zone occupancy + snapshot cache | `24` | New |
| ARZ-064 Rule simulator | `24` | New |
| ARZ-070 Badge templates + designer | `21` | New |
| ARZ-071 Server-side PDF render pipeline | `22` | Replace |
| ARZ-072 Print job queue + failure recovery | `21` | New |
| ARZ-073 Reprint, void, badge history | `21` | New |
| ARZ-074 Photo capture | `23` | New |
| ARZ-041 Complete check-in dual-write cutover | `18` | Refactor |

## Non-engineering gates

This phase is **not deliverable on software alone**.

- **Badge printers and spares** — a printer failing with a queue forming is a business risk (`102`)
- **Badge stock, ribbons, lanyards, holders** — recurring consumables per event (`102`)
- **AGPL licensing position resolved (R1)** — this is the phase where proprietary work starts accumulating

## Exit criteria

| # | Criterion |
|---|---|
| 1 | An accreditation application can be submitted, reviewed, approved and rejected, with an audit trail |
| 2 | An approved application issues a credential whose grants are materialized in under 500ms |
| 3 | A credential is refused at a zone it has no grant for, and the denial is logged with a reason |
| 4 | A credential is refused outside its time window |
| 5 | Re-entry works: the same credential enters, exits, and re-enters, with three log rows |
| 6 | Anti-passback refuses a second entry with no intervening exit when `allow_reentry` is false |
| 7 | A badge renders to PDF at exact mm dimensions in under 2s p95 |
| 8 | A reprint reproduces the **original** template version, not the current one |
| 9 | A voided badge does not revoke its credential; a revoked credential does not un-print its badge |
| 10 | The rule simulator answers 'would this credential get in at this door at this time?' correctly for 20 table-driven cases |
| 11 | Every existing non-cancelled attendee has an ACTIVE credential (backfill assertion) |
| 12 | Check-in reads come from `access_logs`; disabling the new path still restores old behaviour |

## Out of scope

- Offline enforcement (Phase 4)
- RFID/NFC encoding (Phase 4)
- Kiosk badge printing (Phase 4)
- Physical printer integration — the render pipeline is in scope, driving a specific printer is `39`
- Exhibitor and staff credentials beyond the data model (Phase 3, Phase 5)

## Risks

| Risk | Mitigation |
|---|---|
| Approval workflow needs permission delegation that may not exist | Hard dependency on `09` per-event roles from Phase 1 — verify before starting |
| Credential backfill is large and may time out | Batched, idempotent, restartable |
| Badge designer is a substantial UI project | Assess a commercial canvas component before building (`21` open question) |
| Rule engine complexity produces contradictory rule sets | The simulator (ARZ-064) is in scope for this reason, not as a nice-to-have |
| No printers to test against | Waive `128` gate 24 explicitly, with the residual risk recorded |

## Status of this document

SCAFFOLD. Scope, exits and risks are captured from the audit. Expand into work packages
(`137-engineering-work-packages.md`) when the phase is scheduled — a package written
several phases early is fiction.

## Related

`113-roadmap.md` · `116-phase-3.md` · `23-accreditation.md` · `24-access-control.md` · `21-badge-management.md` · `136-master-backlog.md` · `128-definition-of-done.md` · `120-risk-register.md`
