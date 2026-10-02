# Event Readiness Checklist

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process + Derived (the canonical template content `59` loads) + Fix (K1) · **Priority:** P1 as a paper checklist; P3 (ARZ-204) as software · **Phase:** any on paper; 5 in software
**Depends on:** `59-event-readiness.md`, `58-task-management.md`, `106-event-operating-model.md`, `107-event-day-runbook.md`
**Blocks:** nothing — ARZ-204 consumes it

---

The per-event checklist behind the three readiness reviews of `59`. `59` is the system that
evaluates it; this document is **what** it evaluates — the canonical content loaded into `58`'s
templates once ARZ-201 and ARZ-204 exist, and a printable checklist until then. Each item has an
owner role (`106`), a `check_key` where the platform can decide it, and a severity: **B**locking
(no GO without an explicit waiver) or **A**dvisory.

`check_key` convention, from `58`: `domain.predicate`, read-only, returning PASS, WARN or FAIL with
evidence. Keys in plain type are named in `58`. Keys marked **†** are proposed here and join the
registry when the check is built. "Manual" items carry evidence, not a predicate.

## Current state — `MISSING`, ~5%

| Fact | State | Evidence |
|---|---|---|
| Readiness reviews, items, check registry | `MISSING` | `59`; no `readiness_*` or `event_tasks` table (live DB) |
| The only computed checklist | Seven setup items — `tickets`, `schedule`, `publish`, `payouts`, `details`, `customize`, `verify_email` — computed **in the browser** | `SetupChecklist.tsx:90-155` |
| Data most structural checks need | Exists: zones with `requires_credential`, access points, sessions, rooms, accreditations, credentials | `2026_09_29_000002:22,45`; `136` ARZ-020–032, ARZ-050, ARZ-052 |
| Data device, printer and staffing checks need | `MISSING` — no heartbeat, printer registry or shifts | `40`, `39`, `57` |

### Defect

| # | Defect | Evidence | Severity |
|---|---|---|---|
| K1 | **Daily access windows are evaluated in UTC, not venue time.** `AccessScanService` passes `venueTimezone: 'UTC'`; `AccessDecisionService` never reads the field and compares `occurredAt->format('H:i:s')` and `dayOfWeekIso` as given; the application timezone is UTC. `venues.timezone` exists and is unused. At a UTC+3 venue, an 08:00–18:00 grant admits 11:00–21:00 local. Absolute `starts_at`/`ends_at` are unaffected | `AccessScanService.php:83`; `AccessContextDTO.php:34`; `AccessDecisionService.php:205,226`; `config/app.php:147`; `2026_09_29_000001:18` | **High before the first rule with a daily window.** The only test that sets the field uses UTC (`AccessDecisionServiceTest.php:451`), so `37`'s golden vectors would carry the bug to every device. **Fix now**; item 7.4 below requires a non-UTC case |

Also relevant: `107` B1 — an inactive access point still admits, so no check can rely on
`is_active` to mean "closed".

## Review points and who signs

| Point | Purpose | Records the decision | What a NO_GO means |
|---|---|---|---|
| **T-7d** | Structural: timeline, zones, rules, programme, accreditation, staffing plan, venue, kit | DM | A fix list with owners and dates; ED informed |
| **T-24h** | Operational: devices, pre-sync, drill, printers, stock, credentials, rota, paper packs | DM | The last point where anything physical can still be fixed — spares, re-imaging, pre-sync |
| **T-2h — G3** | Live: devices online and synced, gates staffed, network, comms, on-call | **ED** | Doors delayed, or open with named risks |

The scaffold asked two questions.

**How long before doors must readiness be signed off?** G3 at T-2h — the latest point at which a
NO_GO can still delay doors in an orderly way. But blocking hardware items are decided at **T-24h**,
because nothing physical is fixable in two hours; T-2h only confirms live state.

**Who has authority to say no?** Any lead can raise a blocking failure in their area, and a blocking
failure prevents a GO being recorded without a waiver (`59`). **Only the ED waives, and only the ED
records G3** (`106`). The venue safety officer can stop the event under the venue's own procedures,
independently of ARZO. The system never blocks the doors (`59`): it makes the risk visible and owned.

## T-7 days — structural

| # | Item | Owner | `check_key` | Sev |
|---|---|---|---|---|
| 7.1 | Timeline set: build-up start, doors-open, breakdown end (`56`) | DM | `operations.timeline_set`† | B |
| 7.2 | Products exist and the event is published — ticketed events | ED | `setup.tickets`, `setup.publish` (ported from `SetupChecklist.tsx`) | B |
| 7.3 | Every zone with `requires_credential` has an entry access point | SL | `zones.credentialed_have_entry_point`† | B |
| 7.4 | Rule simulation passes for one sample credential per accreditation type — **including a daily window evaluated in venue time** (K1) | SL | `access_rules.simulation_passes` | B |
| 7.5 | Accreditation types, their rules and badge templates approved; a proof printed on the target printer | AL | Manual — the signed proof | B |
| 7.6 | Accreditation applications pending review | AL | `accreditations.none_pending` | A |
| 7.7 | Every published session has a room; no speaker double-booked (`29`) | DM | `sessions.all_have_rooms`; `sessions.no_speaker_clash`† | B |
| 7.8 | Staffing plan: positions and shifts cover every gate and desk open at doors | DM | `shifts.headcount_filled` — rostered | A |
| 7.9 | Network survey done; position map signed (`104`) | TL | Manual — the map | B |
| 7.10 | Kit allocated from `102`'s formulas, N+1 spares, rentals confirmed | TL | Manual — the kit list | B |
| 7.11 | Venue safety certificates, insurance, security plan received | ED | Manual — the documents | B |
| 7.12 | Runbook filled: contacts, positions, radio channels (`107`) | DM | Manual | B |
| 7.13 | Tabletop rehearsal of `107` D1–D8 and F | DM | Manual — who attended | A |
| 7.14 | Deploy freeze scheduled; on-call engineer and a backup named (`106`) | EL | Manual | B |
| 7.15 | Export approvals and client-report obligations agreed | DPL | Manual | A |

## T-24 hours — operational

| # | Item | Owner | `check_key` | Sev |
|---|---|---|---|---|
| 24.1 | Every active access point has an `ACTIVE` device assigned | TL | `access_points.all_have_active_device` | B |
| 24.2 | Full roster pre-sync verified on every device — cursor and count match the server (`103`) | TL | `devices.roster_presynced`† | B |
| 24.3 | App version current on every device; OS updates deferred | TL | `devices.app_version_current`† | B |
| 24.4 | Battery and clock skew within thresholds | TL | `devices.battery_and_clock_ok`† | A |
| 24.5 | Offline drill run on the installed configuration, reconciliation report attached (`104`) | TL | `drills.offline_completed`† — verifies the drill task's evidence | B |
| 24.6 | Canary scans passed at every position at install (`103`) | TL | `canary_scans.all_pass`† — from the canaries' `access_logs` rows | B |
| 24.7 | Printers `READY` with supplies | BL | `printers.ready_with_supplies` | B where printing on demand |
| 24.8 | A hot spare printer enrolled per desk cluster | BL | `printers.spare_enrolled`† | B where printing on demand |
| 24.9 | Pre-print ratio for known attendees at target | BL | `badges.preprint_ratio`† | A |
| 24.10 | Every approved accreditation has an `ACTIVE` credential | AL | `credentials.issued_for_approved` | B |
| 24.11 | Canary credentials issued: valid, wrong zone, revoked, outside hours | AL | `credentials.canaries_present`† | B where access is controlled |
| 24.12 | Rota confirmed: confirmed assignments ≥ required per open gate | DM | `shifts.headcount_filled` — confirmed | B |
| 24.13 | Scan direction set at every bidirectional access point (`54`) | SL | `access_points.direction_configured`† | A |
| 24.14 | Assigned booths built (`35`) | DM | `booths.assigned_are_built` | A |
| 24.15 | UPS and cellular failover tested at every desk and the ops room (`104`) | TL | Manual | B |
| 24.16 | Paper packs printed and sealed: rosters with the row count checked against the dashboard (exports stop at 10,000, `51` R1), runbooks, position cards, deny-list at high-security events | DM | Manual; `roster.fallback_complete`† once rosters are generated server-side | B |
| 24.17 | Accreditation applications still pending | AL | `accreditations.none_pending` | A |

## T-2 hours — live, and the G3 decision

| # | Item | Owner | `check_key` | Sev |
|---|---|---|---|---|
| 2.1 | Every assigned device synced within 15 minutes (`59`; threshold to tune after the pilot) | TL | `devices.synced_recently`† | B |
| 2.2 | Every active access point has an `ACTIVE`, online device | TL | `access_points.all_have_active_device` | B |
| 2.3 | Printers `READY` with supplies | BL | `printers.ready_with_supplies` | B where printing on demand |
| 2.4 | Signed-in staff ≥ required at every gate open at doors (`57` staff sign-in) | GS, reported to DM | `shifts.headcount_filled` — signed in | B |
| 2.5 | Network up; cellular failover armed | TL | Manual | B |
| 2.6 | Radio check complete; contacts sheet current | DM | Manual | B |
| 2.7 | Command center staffed (`106`) | DM | Manual | B |
| 2.8 | Deploy freeze in force; on-call engineer has acknowledged | EL, ENG | Manual | B |
| 2.9 | Venue safety briefing done; venue safety officer consulted | ED | Manual | B |
| 2.10 | **Decision recorded**: GO, GO_WITH_RISKS with each waiver named, or NO_GO | **ED** | Writes the review (`59`) and G3 in `event_gate_decisions` (`56`) | — |

Canary scans at T-60m and the revocation of valid canaries at T-30m follow G3 and live in the
runbook (`107`), not here.

## What can be evaluated when

| Checks | Evaluable |
|---|---|
| `zones.credentialed_have_entry_point`†, `sessions.all_have_rooms`, `sessions.no_speaker_clash`†, `credentials.issued_for_approved`, `accreditations.none_pending`, `setup.*` | **From existing tables** — only the registry is missing (`59` step 1) |
| `access_rules.simulation_passes` | ARZ-064, and only after K1 is fixed |
| `access_points.all_have_active_device`, `devices.*`† | ARZ-100, ARZ-101, ARZ-123 |
| `printers.*`, `badges.preprint_ratio`† | ARZ-121, ARZ-072 |
| `shifts.headcount_filled` | ARZ-200 |
| `drills.offline_completed`†, `canary_scans.all_pass`† | ARZ-102; canaries need a test accreditation type (`23`) |
| `booths.assigned_are_built` | ARZ-131 |
| Manual items | Never — they are evidence, attached to tasks (`58`) |

Until the registry exists, the whole list is run on paper, with the result of each machine check
read by a person from the relevant screen. The paper version is the fallback anyway: `59` requires a
printable review because go/no-go meetings happen where the network is the thing being checked.

## Maintenance

The operations Duty Manager owns this list between events. Closeout lessons (`108`) change it. Every
change states the review point, owner, key and severity, so the template stays loadable by `59`.

## Open questions

- **Thresholds** — 15 minutes of sync age, battery and clock skew are starting guesses (`59`). Tune after the pilot, and record the values in the template, not in code.
- **Client co-signature at T-24h or G3?** A contract term; when it applies, it is evidence on the review.
- **Does a first event at a new venue need a stricter list?** Recommended: 7.13 and 24.5 become blocking for any venue ARZO has not operated before.
- **Advisory items that keep failing** — three events with the same advisory failure is a signal to make it blocking or delete it.

## Related

`59-event-readiness.md` · `58-task-management.md` · `56-event-operations.md` ·
`106-event-operating-model.md` · `107-event-day-runbook.md` · `103-hardware-deployment.md` ·
`104-onsite-infrastructure.md` · `102-hardware-procurement.md` · `24-access-control.md` ·
`37-hardware-integration.md` · `40-device-management.md` · `57-manpower-and-staffing.md` ·
`108-post-event-closeout.md`
