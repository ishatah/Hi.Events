# Phase 118 — Intelligence and Operations

**Status:** SCAFFOLD · **Audit date:** 2026-09-28 · **Complexity:** M–L
**Prerequisite:** Phase 4 complete — all operational data now exists

---

## Objective

Consume what the earlier phases produce: a live command center, real attendance intelligence, post-event reporting, and the ARZO-as-operator workflows.

Nothing here can be pulled forward, because it all depends on data that does not exist until events have run through the full stack. Building predictive features earlier means training on noise.

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-170 Live command center | `53` | New |
| ARZ-171 Attendance, no-show, dwell reporting | `54` | New |
| ARZ-172 Session attendance analytics | `54` | New |
| ARZ-173 Demographics | `52` | New |
| ARZ-222 Automated post-event reports (A1) | `133` | New |
| ARZ-200 Staff, shifts, assignments | `57` | New |
| ARZ-201 Tasks + checklists | `58` | New |
| ARZ-202 Incident management | `60` | New |
| ARZ-203 Vendors + procurement | `61` | New |
| ARZ-204 Readiness gates + go/no-go | `59` | New |
| ARZ-210 Networking + meetings | `31` | New |
| ARZ-211 eRaffle | `31` | New |
| ARZ-212 Live polls + Q&A | `31` | New |
| ARZ-180 CRM integration | `47` | New |
| ARZ-223 Venue heatmaps | `54` | New |

## Exit criteria

| # | Criterion |
|---|---|
| 1 | The command center shows live attendance, occupancy, queues, device and printer health on one screen |
| 2 | The command center **shows its own staleness** when a device has not synced |
| 3 | Every figure on it reconciles to primary `access_logs` records — no stored counters |
| 4 | No-show rate, arrival curve, peak occupancy and dwell time are derivable per event |
| 5 | Per-session attendance and drop-off are reported |
| 6 | A post-event report generates with computed metrics and generated narrative — **no figure produced by a model** |
| 7 | Staff are rostered, assigned, and check in via the same scan path as attendees |
| 8 | An incident is raised, located to a zone, escalated and closed with an audit trail |
| 9 | A readiness checklist blocks go/no-go until machine-checkable items pass |
| 10 | CRM sync pushes attendee and lead data through the public API, not bespoke core code |

## Out of scope

- Predictive attendance and staffing — need multi-event history (`133` A5, A11)
- Cross-event and cross-tenant benchmarking — privacy and contractual questions first (`55`)
- Event digital twin as a distinct deliverable — it is emergent from the data model, not a project (`112`)
- Face recognition — blocked pending legal clearance (`133` A10)
- Vendor marketplace — business-model decision first (`98`)

## Risks

| Risk | Mitigation |
|---|---|
| Command center may outgrow Postgres | `UNVERIFIED` — load-test occupancy and counters first (`74`, `125`) |
| Nobody watches the command center | An operating-model question (`106`), not a software one. Resolve before building the wall display. |
| Operations workflows go unadopted | An unused workflow is worse than none. Validate with the team before building `58` and `59`. |
| AI report generation fabricates figures | Template all metrics; generate only narrative. Non-negotiable guardrail (`133`). |
| Demographics collected without basis | Consent-based, opt-in per event, retention defined (`65`) |

## Status of this document

SCAFFOLD. Scope, exits and risks are captured from the audit. Expand into work packages
(`137-engineering-work-packages.md`) when the phase is scheduled — a package written
several phases early is fiction.

## Related

`113-roadmap.md` · `53-live-event-command-center.md` · `54-attendance-intelligence.md` · `56-event-operations.md` · `133-ai-capabilities.md` · `136-master-backlog.md` · `128-definition-of-done.md` · `120-risk-register.md`
