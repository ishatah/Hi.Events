# Live Event Command Center

**Status:** WRITTEN · **Audit date:** 2026-09-29 (re-verified; first written 2026-09-28) · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P2 (ARZ-170) · **Phase:** 5
**Depends on:** `71` (realtime), `24` (access logs), `40` (devices), `20` (queues), `52` (analytics)

---

## Purpose

One screen that is the operational source of truth on event day. Today there is no such screen, and
nothing to build it from.

## Current state — `MISSING`, blocked on infrastructure

`CONFIRMED`: there is **no realtime transport** — `config/broadcasting.php` is Laravel's untouched
stub, the codebase reads the pre-Laravel-11 `BROADCAST_DRIVER` key (set to `log` where set at all),
no event implements `ShouldBroadcast`, `routes/channels.php` still references the non-existent
`App\Models\User` namespace, and there is no echo/pusher client in the frontend.

Re-verified 2026-09-29, unchanged: `BROADCAST_DRIVER=log` in `backend/.env.example:48`,
`BROADCAST_CONNECTION` appears nowhere, `BroadcastServiceProvider` is commented out
(`config/app.php:242`), `channels.php` holds only the stub `App.Models.User.{id}` channel, and there
is no Reverb, Pusher or Echo package on either side.

What exists instead is client polling — four hooks: `usePollGetOrderPublic` (5 s, always), answers
export (5 s while running), occurrence generation (2 s while running), VAT validation (5 s while
pending). Plus `StatsTab`, which computes a 5-minute throughput window client-side per check-in
list — and **cannot display more than 4 per minute**, because it derives the rate from the 20 most
recent check-ins the endpoint returns (`51` R7). The stats query itself is not polled; it refreshes
only when a check-in on that device invalidates it.

So the command center is blocked on **new infrastructure**, not new queries. Step zero is the
`BROADCAST_DRIVER` → `BROADCAST_CONNECTION` migration and deleting the dead channel scaffold.

### What landed since the first revision

`c34f6a59` put the command center's data layer in the schema, and `e7228c1d` gave `access_logs` its
first writer:

| Table | State |
|---|---|
| `access_logs` | Written by `AccessScanService` (`e7228c1d`); its HTTP endpoint was uncommitted at audit time, and neither check-in path dual-writes yet (ARZ-041). **No `device_id` column** — added by `40`'s follow-on migration. |
| `zone_occupancy_snapshots` | Exists: `zone_id`, `event_id`, `occupancy`, `capacity`, `captured_at`. No writer. |
| `access_point_throughput_snapshots` | Exists, per `20`. No writer. |
| `devices` | Exists with `last_seen_at`, `battery_level`, `last_sync_cursor`, `app_version`. No heartbeat endpoint. |
| `AccessDecisionService` | A pure function of an `AccessContextDTO` with 35 tests — the denial reasons this screen breaks out come from its `AccessResult` enum |

The remaining gap is the writers — the snapshot jobs (`52`), the heartbeat (`40`), the dual-write of
check-ins into `access_logs` (ARZ-041) — and the transport.

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
the authoritative fallback. Denial breakdowns use the `AccessResult` values —
`DENIED_NO_GRANT`, `DENIED_TIME_WINDOW`, `DENIED_CAPACITY`, `DENIED_ANTIPASSBACK`,
`DENIED_MAX_ENTRIES`, `DENIED_REVOKED`, `DENIED_RULE`, `DENIED_NO_CREDENTIAL` — so each spike maps
to a cause rather than a single "denied" line.

Figures from a window in which any device has not yet synced are marked **provisional** (`52`).

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
`40-device-management.md` · `51-reporting.md` · `52-analytics.md` · `54-attendance-intelligence.md` ·
`57-manpower-and-staffing.md` · `60-incident-management.md` · `86-monitoring.md` ·
`97-onsite-operations-app.md`
