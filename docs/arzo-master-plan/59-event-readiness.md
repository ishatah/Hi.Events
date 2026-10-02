# Event Readiness and Go / No-Go

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New · **Priority:** P3 (ARZ-204) · **Phase:** 5; the check registry starts earlier
**Depends on:** `58-task-management.md`, `40-device-management.md`, `56-event-operations.md`
**Blocks:** `131-event-readiness-checklist.md`

---

## Current state — `MISSING`

`CONFIRMED`: no readiness, go/no-go or runbook entity; searches for those terms return nothing.

What exists is the **publish** check — account verified, not under spam review — plus Stripe and
products checks that run only in the browser (`56` O2). Publishing answers "can this go on sale?".
Readiness answers a different question: **"can the doors open?"**

## What readiness is

A scheduled review before the event, producing a **recorded decision** with the evidence it was
made on. Three properties:

1. **Most of it is machine-checkable.** Devices enrolled and synced, printers ready, credentials
   issued, rules simulated — the platform knows these facts better than a person with a clipboard.
2. **The rest carries evidence.** A fire certificate is a document, not a tick.
3. **The decision is a human's, and it is recorded.** Who decided, when, on what, and what risks they
   accepted.

## Review points

Anchored to doors-open (`56`):

| Point | Purpose |
|---|---|
| **T-7 days** | Structural: programme, zones, rules, accreditations, staffing plan |
| **T-24 hours** | Operational: devices enrolled and fully synced, printers and stock, badges pre-printed, rota confirmed |
| **T-2 hours** | Live: devices online, gates staffed, network up, offline drill done — **the G3 go / no-go** |

## The checks

Drawn from the registry shared with `58`. Each has a severity: **blocking** (a failure means
"no-go unless explicitly waived") or **advisory**.

| Area | Check | Severity |
|---|---|---|
| Access | Every active access point has an `ACTIVE` device assigned (`40`) | Blocking |
| Access | Rule simulation passes for one sample credential per accreditation type (ARZ-064) | Blocking |
| Access | No zone with `requires_credential` lacks an entry access point | Blocking |
| Devices | All assigned devices synced within the last 15 minutes; app version current | Blocking at T-2h |
| Devices | Battery above threshold; clock skew below threshold | Advisory |
| Offline | An offline drill was run on this venue's configuration — `71`'s emergency mode exercised | Blocking at T-24h |
| Badges | Printers `READY` with supplies; spare printer enrolled | Blocking where on-demand printing is used |
| Badges | Pre-print ratio for known attendees at target | Advisory |
| Credentials | Every approved accreditation has an `ACTIVE` credential | Blocking |
| Accreditation | No applications pending review | Advisory |
| Programme | Every published session has a room; no speaker clash (`29`) | Blocking |
| Staffing | Rostered headcount at or above required, per open gate (`57`) | Blocking |
| Exhibitors | Assigned booths `BUILT` (`35`) | Advisory |
| Data | Scan direction set on every bidirectional access point (`54`) | Advisory |
| Manual | Venue safety certificates, insurance, security briefing | Per event, with evidence |

The list is a starting point and belongs in templates (`58`), so each event type carries its own.

## Decision: the system never blocks the doors

The scaffold asked whether the system can block an event from opening. **It must not.**

The event happens whatever the software says. A platform that refuses to open gates because a check
failed produces exactly the failure offline-first exists to prevent (`71`): a door that does not
work, with a queue attached. The system's job is to make the risk **visible and owned**, not to
override the person responsible for the event.

So: blocking checks prevent a `GO` decision from being recorded **without an explicit waiver**,
naming who waived what and why. A `NO_GO` or an overridden `GO` is recorded like any other decision.
Nothing is locked.

## Model

```
readiness_reviews
  id, short_id, event_id,
  review_point,        -- T_MINUS_7D | T_MINUS_24H | T_MINUS_2H | AD_HOC
  decision,            -- PENDING | GO | GO_WITH_RISKS | NO_GO
  decided_by NULL → users, decided_at NULL, notes NULL,
  timestamps

readiness_items        -- a snapshot, frozen when the decision is made
  id, readiness_review_id,
  check_key NULL, event_task_id NULL → event_tasks,
  title, severity,     -- BLOCKING | ADVISORY
  status,              -- PASS | WARN | FAIL | WAIVED | NOT_APPLICABLE
  detail jsonb, evidence jsonb NULL,
  waived_by NULL, waiver_reason NULL,
  evaluated_at
```

**The snapshot is the point.** Checks change minute to minute; the review records what was true
when the decision was made. After an incident, "was Gate 3's device synced at the go/no-go?" has an
answer. The G3 decision is also written to `event_gate_decisions` (`56`).

## The readiness screen

One page per review point: blocking failures first, then warnings, then passes collapsed. Each
failure links to the thing that fails — the unsynced device, the gate without staff. A **printable
version** exists because go/no-go meetings happen in rooms where the network is the thing being
checked.

## Relationship to `131`

`131` is the per-event checklist **document**. This is the system that evaluates it. Once built,
`131` becomes the canonical template content loaded into `58`.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | Check registry with the seven setup-checklist checks ported server-side | With `58` — can precede Phase 5 |
| 2 | Checks added as each domain lands | Phases 2–4 |
| 3 | `readiness_reviews`, `readiness_items`; the readiness screen | Phase 5 |
| 4 | Scheduled evaluation at T-7d, T-24h, T-2h with a digest to the event owner | Phase 5 |

## Open questions

- **Who owns go / no-go?** `106` — the system records, it does not decide.
- **Thresholds** — sync age, battery, clock skew. Initial values are guesses; tune after the pilot.
- **Is the offline drill a check or a task?** A task with evidence (the reconciliation report) that a check then verifies exists.
- **Client sign-off** — do clients see or co-sign the T-24h review? Government clients may require it.

## Related

`58-task-management.md` · `56-event-operations.md` · `40-device-management.md` ·
`39-printer-integration.md` · `57-manpower-and-staffing.md` · `71-realtime-architecture.md` ·
`106-event-operating-model.md` · `107-event-day-runbook.md` · `131-event-readiness-checklist.md`
