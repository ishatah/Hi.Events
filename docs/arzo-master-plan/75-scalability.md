# Scalability

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix (three live constraints) + Process (load model) · **Priority:** P1; K1 and K2 fix now; load model before Phase 4 — all unnumbered, add to `136` when scheduled · **Phase:** any
**Depends on:** `74-performance.md`, `71-realtime-architecture.md`, `84-infrastructure.md`
**Blocks:** `125-performance-testing-plan.md`, `76-reliability.md`

---

## Current state — capacity `UNVERIFIED`; the mechanics are `CONFIRMED`

Nobody has measured how far this system goes. What the audit can do is find the places where cost
grows faster than load. There are three, and one of them is in the code that landed this week.

| Fact | Evidence |
|---|---|
| k6 scripts exist for the checkout funnel (with an oversell check) and the event page; **no results are recorded** and **no script exercises scanning, sync or check-in** | `misc/k6/README.md`, `checkout-flow.js`, `event-page.js` (`6fadf9b2`, upstream) |
| Order creation holds `pg_advisory_xact_lock(event_id)` for its whole transaction | `CreateOrderHandler.php:56-57` |
| **The same key** is taken by event update, occurrence generation and waitlist processing | `UpdateEventHandler.php:74`; `GenerateOccurrencesFromRuleHandler.php:29`; `ProcessWaitlistService.php:67,141` |
| `AccessScanService` computes zone occupancy on **every** scan with a zone: all `GRANTED` logs for the zone, grouped by credential, every group fetched into PHP, filtered and counted there | `AccessScanService.php:90,174-186` |
| It does so whether or not the zone has a capacity; `AccessDecisionService` enforces capacity whenever `zones.capacity` is set and does not read `access_rules.enforce_capacity` | `AccessDecisionService.php:137-143,304-308` |
| One rate limiter on every API route: 180 requests/min per user, **or per IP when unauthenticated** | `RouteServiceProvider.php:27-29`; `config/app.php:27`; `Kernel.php:71` |
| The public scanner is unauthenticated, and one scan costs up to **four** requests: attendee lookup, check-in, and refetches of the check-in list and its stats | `CheckIn/index.tsx:351`; `useCreateCheckInPublic.ts:67-75` |
| Cache defaults to `file` — the rate limiter, job uniqueness and page-view buffer are per node unless a shared store is configured | `cache.php:18`; `.env.example:47` |
| No read replica, no pooler in `config/database.php` | Search for `'read'`, `sticky`, pooler |
| The only Lambda sizing in the repository is upstream's: `concurrency: 100`, `queue-concurrency: 5`, `warm: 3` | `vapor.yml:12-21` — upstream Hi.Events, not ARZO |

## Constraints

| # | Constraint | Evidence | Severity |
|---|---|---|---|
| K1 | **The scan path's occupancy query is O(people who entered the zone), per scan.** A 10,000-person zone transfers ~10,000 rows into PHP on every scan; over an arrival spike the work grows roughly with the square of arrivals. It cannot meet `74`'s online p95 < 150 ms. | `AccessScanService.php:174-186` | **High** — fix before ARZ-041 routes check-ins through it |
| K2 | **The current scanner throttles itself at a modest door.** Devices behind one venue NAT share one 180/min budget; at four requests per scan that is **~45 scans/min per egress IP**, a single busy gate. Whether a venue presents one IP is `UNVERIFIED` per venue (`104`), but it is the common case. | Rate limiter above | **High** for any event using today's scanner — fix now |
| K3 | Checkout for one event is fully serialized, **and** serialized with organizer edits, occurrence generation and waitlist offers on the same key. Throughput per event is 1 ÷ lock hold time, `UNVERIFIED`. | Four call sites above | Medium — measure first (`misc/k6` reports it as `step_reserve` p95 rising with rate) |
| K4 | On a multi-node host, per-node file cache multiplies the rate limit by the node count and breaks job uniqueness (`70` J9) | `cache.php:18` | Medium — a host decision |

### Fix K1 — derived, bounded, and only when needed

Consistent with `25`, `52` and `74`, and without introducing a counter:

1. Compute occupancy **only when it can change the decision**: the zone has a capacity and, once
   the rule engine reads it, an active rule has `enforce_capacity`.
2. **Aggregate in SQL** — `count(*)` over credentials whose net entries are positive — never
   transfer rows.
3. On the hot path read `zone_occupancy_snapshots` (refreshed every 5–10 s, `70`) **plus the delta
   of logs since `captured_at`**, which is bounded by seconds of traffic. The full aggregate remains
   the authoritative fallback when no fresh snapshot exists.

Still derived from the append-only log; the snapshot is a cache the log can always rebuild.

### Fix K2 — limit devices by device, not by IP

A separate limiter for scanner and device traffic, keyed by check-in list (today) and by device key
(`40`) once devices exist, with a budget sized to the door rather than to an anonymous browser. The
global per-IP limiter stays for anonymous public traffic. Trimming requests per scan helps too: the
check-in list itself does not change when someone is admitted, so refetching it after every scan is
waste.

### Fix K3 — measure, then narrow the lock

Separate namespaces with the two-integer form already used by `LocationLockService`, so checkout
does not wait on an organizer saving the event description. Whether event updates must serialize
with checkout at all (capacity changes do; title changes do not) is a question to answer per field.

## Decision: model load by arrival curve, not by attendee count

`74` states the principle; this is the arithmetic, with **provisional** inputs:

```
peak scans/min ≈ attendees × share arriving in the peak window ÷ window minutes
                 × peak-minute factor × (1 + denial and re-scan rate)

10,000 × 0.6 ÷ 30 × 2 × 1.2 ≈ 480 scans/min ≈ 8 scans/s
```

`74` assumed 600/min; the two agree in order of magnitude, which is all an assumption can do. The
largest event ARZO intends to run (`04`) replaces every input.

## Decision: offline-first is the scaling strategy

The same event costs the server very different amounts under the two architectures:

| | Online scanning (today) | Offline-first devices (`71`) |
|---|---|---|
| Server requests driven by | **Scans** | **Devices** |
| At 480 scans/min | ~1,900 req/min, each running the scan path | 20 devices × (sync every 15 s + heartbeat every 30 s) ≈ **2 req/s**, independent of the spike |
| Database writes | One `access_logs` row per scan, synchronously | The same rows, batched per sync |
| During a spike | Latency at the door rises with load | Door latency is local; only sync lag rises |

The arrival spike moves from the server to the devices, which have nothing else to do. This is why
the plan does not answer event-day scale with more servers or extracted services (`05`).

## Load drivers and ceilings

| Driver | Scales with | Ceiling today | Response |
|---|---|---|---|
| On-sale checkout | Concurrent buyers **per event** | One lock per event (K3) | Measure; narrow the lock; a waiting room only if an event ever needs one |
| Online scans | Scans × occupancy cost | K1, K2 | Fixes above; offline-first |
| Sync and heartbeat | Devices | None measured | Batch caps (`71`) |
| Snapshot jobs | Zones × frequency | Not built | One query per event covering all its zones (`70`) |
| Command center | Viewers × refresh | Polling | 1 Hz broadcast aggregates (`53`) |
| Notification fan-out | Recipients | Provider rate — `UNVERIFIED` per provider | `notify` pool (`70`) |
| Badge pre-print | Badges | CPU | `bulk` pool (`70`) |
| Postgres connections | App concurrency + workers | Unset for ARZO | A pooler sized to the host (`84`) |
| Concurrent events | Events sharing one Postgres | `74` assumes 5 | Priority for event-day pools; no per-tenant isolation planned |

## Postgres stays the shared bottleneck — with triggers

A single primary serves checkout, scans, dashboards and exports. That is fine until a trigger fires;
`52` names the analytics triggers, and these complete them:

| Trigger | Response |
|---|---|
| Dashboard or report queries appear in the slow-query log during an event | Route them to a read replica |
| `access_logs` scans exceed the snapshot interval | Partition by `event_id` (`74`) |
| Connection count within 80% of the maximum at peak | Pooler in transaction mode; cap app concurrency |
| Lock wait on the checkout key exceeds 500 ms p95 at on-sale | K3 narrowing, then per-product locks |

## Targets

Provisional, like `74`: **sustain 2× the modelled peak** of the largest intended event with `74`'s
p95 targets intact, and with a second event on-sale at the same time. Headroom is what absorbs the
modelling error.

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | K2: device/check-in-list limiter; drop the check-in-list refetch after each scan | Low — **now** |
| 2 | K1: conditional, SQL-aggregated occupancy; snapshot + delta when snapshots exist | Low — **before** ARZ-041 routes check-ins through `AccessScanService` |
| 3 | k6 scripts for scan, sync and heartbeat; run the existing checkout spike against a production-like host and record the results (`125`) | Low |
| 4 | K3 lock namespaces; measure lock wait (`78`) | Medium — touches checkout |
| 5 | Shared cache store and pooler on the chosen host (K4) | With `84` |

## Open questions

- **What is the largest event, and how many run at once?** `04` — every number here waits on it.
- **Venue network topology** — one NAT per venue, bandwidth, captive portals (`104`). K2's severity depends on it.
- **Does ARZO sell on-sale spikes** (a popular concert) or mostly invited and accredited audiences? The latter makes K3 a low priority.
- **Who runs `125`, against what environment?** Load-testing upstream's production is not an option; ARZO needs its own staging (`77` D2).

## Related

`74-performance.md` · `71-realtime-architecture.md` · `125-performance-testing-plan.md` · `70-background-jobs.md` ·
`52-analytics.md` · `25-zones-and-permissions.md` · `24-access-control.md` · `40-device-management.md` ·
`84-infrastructure.md` · `104-onsite-infrastructure.md` · `05-product-architecture.md`
