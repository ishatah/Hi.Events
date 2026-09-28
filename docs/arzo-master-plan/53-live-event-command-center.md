# Live Event Command Center

**Status:** WRITTEN · **Audit date:** 2026-09-28
**Classification:** New subsystem
**Depends on:** `71` (realtime), `24` (access logs), `40` (devices), `20` (queues)

---

## Purpose

One screen that is the operational source of truth on event day. Today there is no such screen, and
nothing to build it from.

## Current state — `MISSING`, blocked on infrastructure

`CONFIRMED`: there is **no realtime transport** — `config/broadcasting.php` is Laravel's untouched
stub, the codebase reads the pre-Laravel-11 `BROADCAST_DRIVER` key (set to `log` where set at all),
no event implements `ShouldBroadcast`, `routes/channels.php` still references the non-existent
`App\Models\User` namespace, and there is no echo/pusher client in the frontend.

What exists instead is client polling: `usePollGetOrderPublic` at 5s, occurrence-generation polling,
export polling, VAT polling. Plus `StatsTab`, which computes a 5-minute throughput window
client-side per check-in list.

So the command center is blocked on **new infrastructure**, not new queries. Step zero is the
`BROADCAST_DRIVER` → `BROADCAST_CONNECTION` migration and deleting the dead channel scaffold.

## What it must show

Grouped by the question an operator is actually asking.

**Are people getting in?**
- Registered / checked in / not arrived, with percentage
- Arrival rate now vs expected curve
- Per-access-point throughput and queue depth (`20`)
- Denial rate, with reasons broken out — a spike in `DENIED_NO_GRANT` means a misconfigured rule, not a crowd problem

**Is the equipment working?**
- Device fleet: online, last sync, battery, app version (`40`)
- Printers: online, queue depth, stock warnings, failures (`21`)
- Devices in degraded/offline mode, and for how long

**Where are people?**
- Occupancy per zone against capacity (`25`)
- Session attendance vs registration (`27`)
- Capacity warnings before they become incidents

**What is going wrong?**
- Access violations and overrides, with operator attribution
- Open incidents by severity (`60`)
- Retrospective violations surfaced by offline reconciliation (`71`)

**Who is working?**
- Staff checked in vs rostered (`57`)
- Unfilled positions at open gates

## Design

### Transport
Reverb over WebSockets (`71`), channel `private-event.{eventId}.operations`, authorized by the same
tenancy rules as HTTP — a channel leak is a cross-tenant breach.

**Throttle aggressively.** A busy gate produces many scans per second; broadcasting each to a
dashboard is waste. Aggregate counters to 1 Hz; broadcast individual events only for exceptions
(denials, device offline, incidents, capacity breach).

### Data sourcing
Counters derive from `access_logs` (`24`), never from stored counters that drift under offline replay.
Occupancy uses the `zone_occupancy_snapshots` cache refreshed every 5–10s, with the live aggregate as
the authoritative fallback.

`UNVERIFIED`: whether Postgres serves this at peak. Needs load modelling (`74`, `125`) before
committing to the refresh interval.

### Degraded honesty
The command center must show its **own** staleness. If a device has not synced for 20 minutes, the
figures from that gate are 20 minutes old and the screen must say so. A dashboard that looks live
while showing stale data is worse than one that admits it.

This is the same principle as emergency mode in `71`: the operator must know what they do not know.

## Alerts

Alert on what attendees feel, not on infrastructure noise:

| Condition | Severity |
|---|---|
| Queue above threshold at any access point | High |
| Zone at or above capacity | High |
| Printer offline or out of stock with a queue | High |
| Device offline more than N minutes | Medium |
| Denial rate spike | Medium |
| Session at capacity with people waiting | Medium |
| Staff position unfilled at an open gate | Medium |

Routing and on-call are `86`. Alerts without a watcher are decoration.

## Open questions

- **Does this need a read-optimized store, or will Postgres serve?** Depends on `74`. Do not build an OLAP pipeline speculatively.
- **Screen or app?** Probably both — a wall display for the ops room and a mobile view for supervisors walking the floor (`97`).
- **Who watches it?** A command center with nobody in front of it is theatre. This is an operating-model question (`106`), not a software one.
- **Floor-plan map** — needs a real coordinate system decision (`25` open question).

## Related

`71-realtime-architecture.md` · `24-access-control.md` · `20-queue-management.md` ·
`40-device-management.md` · `54-attendance-intelligence.md` · `60-incident-management.md` ·
`86-monitoring.md` · `97-onsite-operations-app.md`
