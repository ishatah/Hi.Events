# Phase 5 — Intelligence and Operations

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d` · **Complexity:** M–L
**Classification:** Derived (from `113`, `136`) · **Prerequisite:** Phase 4 complete — all operational data now exists
**Depends on:** `53-live-event-command-center.md`, `52-analytics.md`, `54-attendance-intelligence.md`, `56-event-operations.md`, `57-manpower-and-staffing.md`, `58-task-management.md`, `59-event-readiness.md`, `60-incident-management.md`
**Blocks:** nothing — the last phase

---

## Objective

Consume what the earlier phases produce: a live command center, attendance intelligence, generated
post-event reporting, and the ARZO-as-operator workflows that make the third event cheaper to run than
the first.

Nothing here can be pulled forward wholesale, because it depends on data that does not exist until
events have run through the full stack. Two exceptions are worth taking early, because they are cheap
and independent: the server-side **check registry** seeded from today's setup checklist (`58`, `59`),
and the **statistics repair command** (`52`).

## Where it stands

| Area | State at `e7228c1d` |
|---|---|
| Snapshot tables | `zone_occupancy_snapshots`, `access_point_throughput_snapshots` exist; no writer |
| Attendance data | `access_logs` written only by `AccessScanService`; neither check-in path yet (ARZ-041) |
| Operations | Nothing — no staffing, tasks, readiness, incidents, vendors; `staff.manage` and `incident.manage` are seeded permission strings nobody reads |
| Audit spine | `event_logs` never written, `entity_type` mistyped; `order_audit_logs` has no actor (`67`) |
| Realtime | None (`53`) |

## Decisions carried in

| Topic | Decision | Doc |
|---|---|---|
| Metric sourcing | Attendance from logs, never counters; snapshots are caches; groups < 5 suppressed | `52`, `54` |
| Late data | Figures are **provisional until settled** — every active device synced past the window | `52`, `53` |
| Exits | Dwell and true occupancy only where exits are scanned; reports say so rather than invent | `54` |
| Operations record | `event_operations` beside `events`; `events.status` stays a commerce state | `56` |
| Gates | G1–G4 in append-only `event_gate_decisions` | `56` |
| Readiness | **Never blocks the doors.** Blocking checks prevent recording GO without a named waiver | `59` |
| Staff | Credentials via `STAFF` accreditation; shifts with a person/time exclusion constraint; sign-in by scanning one's own credential | `57` |
| Incidents | Append-only timeline; alerts propose, humans confirm; `RESTRICTED` sensitivity | `60` |
| Benchmarks | `event_benchmark_facts` written at closeout, no personal data, outlive the raw logs | `55` |
| AI | Narrative only; every figure computed conventionally | `133` |

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-170 Live command center | `53` | New |
| ARZ-171 Attendance, no-show, dwell reporting | `54` | New |
| ARZ-172 Session attendance analytics | `54` | New |
| ARZ-173 Demographics with small-group suppression | `52` | New |
| ARZ-222 Post-event report bundle with generated narrative (A1) | `51`, `108`, `133` | New |
| ARZ-200 Staff, positions, shifts, sign-in | `57` | New |
| ARZ-201 Tasks, templates, automatic checks | `58` | New |
| ARZ-204 Readiness reviews + go/no-go | `59` | New |
| ARZ-202 Incident management | `60` | New |
| ARZ-203 Vendors + procurement (cost capture only) | `61`, `62` | New |
| `event_operations`, gate decisions, timeline anchors (unnumbered) | `56` | New |
| Event dossier (unnumbered) | `63` | New |
| Sponsor fulfilment evidence | `34` | New |
| ARZ-210 Networking + buyer↔exhibitor meetings | `31` | New |
| ARZ-211 eRaffle (unless pulled into Phase 3) | `31` | New |
| ARZ-212 Polls + Q&A — **integrate, do not build** | `31` | Integrate |
| ARZ-180 CRM — the one ARZO uses, push-only | `47` | Integrate |
| ARZ-223 Venue heatmaps | `54` | New |
| `event_benchmark_facts` + within-account benchmarks (unnumbered) | `55` | New |

## Exit criteria

| # | Criterion |
|---|---|
| 1 | The command center shows arrivals, occupancy, queue estimates, device and printer health on one screen |
| 2 | It **shows its own staleness**: a gate whose devices have not synced is marked with its data age |
| 3 | Every figure reconciles to `access_logs` — a spot check recomputes three figures from the logs |
| 4 | Show rate, arrival curve, re-entries and peak occupancy are reported per event; dwell only where exits are scanned, and the report says which |
| 5 | Session attendance versus registration and drop-off are reported |
| 6 | A post-event report generates with computed figures and generated narrative, and **no figure originates in a model** |
| 7 | A person cannot be rostered to two overlapping shifts — database-enforced |
| 8 | Staff sign in by scanning their own credential; the same scan identifies them as operator on a scanner |
| 9 | An incident is raised offline, located to a zone, escalated on overdue acknowledgement, closed with a reason; restricted incidents show only as counts to other roles |
| 10 | A readiness review with a failing blocking check **cannot be recorded as GO without a named waiver**, and nothing is locked by it |
| 11 | Readiness items are frozen into a snapshot at decision time |
| 12 | Closeout writes `event_benchmark_facts`, and a benchmark renders against at least three comparable past events |
| 13 | CRM sync pushes through the public API and working webhooks — no CRM-specific code in the core |

Criterion 10 replaces the earlier "blocks go/no-go until checks pass", which would have made the
software able to stop an event opening — the failure `71` exists to prevent (`59`).

## Out of scope

- Predictive attendance and staffing — multi-event history first (`133` A5, A11; `134`)
- Cross-tenant benchmarking — contractual and privacy questions first (`55`)
- A digital twin as a deliverable — it is emergent from the data model (`112`)
- Face recognition — legal clearance first (`133` A10)
- A vendor marketplace, a project-management tool, an accounting system (`04`)

## Risks

| Risk | Mitigation |
|---|---|
| Postgres cannot serve the command center at peak | Load-test snapshots against `52`'s triggers first (`125`) |
| Nobody watches the command center | Operating-model question (`106`) — resolve before building the wall display |
| Operations workflows go unadopted | Start with timeline, G3 and templates only (`56`); validate each with the team |
| Generated narrative misstates a figure | Figures templated in; the model sees only computed values (`133`) |
| Movement data misused | Individual trails behind `access.logs.view` with audited views (`54`, `67`) |

## Related

`113-roadmap.md` · `117-phase-4.md` · `51-reporting.md` · `52-analytics.md` ·
`53-live-event-command-center.md` · `54-attendance-intelligence.md` · `55-event-intelligence.md` ·
`56-event-operations.md` · `57-manpower-and-staffing.md` · `58-task-management.md` ·
`59-event-readiness.md` · `60-incident-management.md` · `108-post-event-closeout.md` ·
`133-ai-capabilities.md` · `136-master-backlog.md`
