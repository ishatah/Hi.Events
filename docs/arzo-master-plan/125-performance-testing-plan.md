# Performance Testing Plan

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Process · **Priority:** P1 (proves ARZ-063, ARZ-101, ARZ-071, ARZ-104) · **Phase:** scenario 1 now; the rest as each path lands
**Depends on:** `74-performance.md`, `75-scalability.md`, `71-realtime-architecture.md`, `24-access-control.md`
**Blocks:** `127-production-readiness.md`, `130-launch-checklist.md`

---

`74` states targets derived from perception and consequence. This document is how they get proved
or disproved: which load, on which path, in which environment, with which pass line, in which order.
It tests the arrival spike, not the average.

## Current state — `PARTIAL`, ~10%: tooling exists, evidence does not

`74` and `140` say "no load-testing evidence". That is right about results and wrong about tooling.

| Fact | State | Evidence |
|---|---|---|
| k6 scripts for the buyer funnel | `CONFIRMED` | `misc/k6/checkout-flow.js` (smoke/steady/spike, capped-product oversell check `:267-292`), `misc/k6/event-page.js`, `misc/k6/README.md`; added **upstream** in `6fadf9b2` (2026-08-23), not by ARZO |
| Their thresholds disagree with `74` | `CONFIRMED` | reserve/complete p95 < 1500 ms (`checkout-flow.js:69-70`) versus `74`'s order creation < 1 s |
| Any other load tool | `MISSING` | Search `artillery\|locust\|jmeter\|gatling` → only `misc/k6/README.md` |
| Any recorded result | `MISSING` | No results file, report or document anywhere |
| Scripts for scan, sync, occupancy, badges, command center | `MISSING` | The scan endpoint is uncommitted, in progress at audit time; sync, badge render and realtime do not exist |
| An environment to test | `MISSING` | `backend/vapor.yml` (app `HiEvents`, `api.hi.events`) and `deploy.yml` are upstream's, unchanged by ARZO's 14 commits; ARZO has no staging or production (`126`) |
| Global limiter | `CONFIRMED` | 180/min keyed by user id **or IP** (`RouteServiceProvider.php:27-30`, `config/app.php:27`); applied to the whole `api` group (`Http/Kernel.php:70-71`); the dev stack raises it to 100,000 (`misc/k6/README.md:20`) |
| Per-event advisory lock | `CONFIRMED` | `CreateOrderHandler.php:57`; the same key is taken by waitlist offers (`ProcessWaitlistService.php:67,141`), event edits (`UpdateEventHandler.php:74`) and occurrence generation (`GenerateOccurrencesFromRuleHandler.php:29`) |

## Defects and predictions from code reading

| # | Finding | Evidence | Severity |
|---|---|---|---|
| L1 | **Occupancy is recomputed on every scan, in PHP.** Any scan whose access point has a zone runs the aggregate — even when the zone has no capacity. It groups every GRANTED log for the zone by credential and `->get()`s every group into PHP to count. Per-scan cost grows with credentials that have entered; across an arrival spike total work is roughly quadratic | `AccessScanService.php:90,174-186` | **High**, latent — predicts a miss of `74`'s 150 ms p95 at a 10,000-person zone. Tracked as ARZ-307 |
| L2 | **A whole venue shares one 180/min budget.** Public scanner routes are unauthenticated, so the limiter keys on IP; venue devices usually egress through one NAT address; each scan costs one request, or two when the attendee is not in the loaded page — the common case at a large event (`CheckIn/index.tsx:346-351,232`). The ceiling is roughly 90–180 scans/min per venue, against `74`'s working peak of 600 | `api.php:655-661` (baseline); `RouteServiceProvider.php:28-29` | **High**, live today — a hypothesis until scenario 2 confirms it |
| L3 | Replay idempotency is check-then-insert, not atomic: two concurrent submissions of one `client_generated_id` both pass the check, and one fails the UNIQUE index as a 500 | `AccessScanService.php:47-58,211-228` | Medium — no data loss (the device retries), but noise exactly during a sync storm |
| L4 | The checkout lock is shared: a waitlist offer or an organizer saving the event serializes with an on-sale | Advisory lock rows above | Medium — by design; measure it |
| L5 | Stripe's webhook calls count against the same per-IP limit, so a sales spike can 429 Stripe's delivery | `api.php:652` (baseline) inside the `api` group | Medium — Stripe retries, but payment confirmation lags; `UNVERIFIED` until scenario 3 |

L2–L5 have no backlog id — unnumbered, add to `136` when scheduled. L2 is the one to fix now,
independent of the roadmap.

The fix for L1, stated so the test has something to prove:

1. Compute occupancy only when the zone has a capacity **and** a matching grant or rule enforces it (`24`'s `enforce_capacity`).
2. Aggregate in SQL — `count(*)` over the grouped subquery — never materialize groups in PHP.
3. On the hot path read `zone_occupancy_snapshots` (the table exists since `c34f6a59`; nothing writes it) plus the entries and exits since `captured_at`, which the `(zone_id, occurred_at)` index serves as a short range scan. The full live aggregate is the fallback when the snapshot is stale.

## Decision: event profiles are parameters, and the largest one is missing

Every target in `74` is meaningless without a profile. The only anchor today is `74`'s working
assumption, so it becomes profile **M**. S and L bracket it; they are test parameters, not forecasts.

| Parameter | S (M ÷ 5) | **M** (`74`) | L (M × 4) | XL |
|---|---|---|---|---|
| Attendees | 2,000 | 10,000 | 40,000 | **The largest event ARZO intends to run — `04`, unanswered** |
| Scanners | 4 | 20 | 80 | |
| Peak scans/min, all gates | 120 | 600 | 2,400 | |
| Concurrent checkout sessions | 100 | 500 | 2,000 | |
| Devices reconnecting at once | 10 | 50 | 200 | |
| Concurrent live events | 5 | 5 | 5 | |

- **Test at twice the profile's peak.** Arrival curves are bursty; a system that passes exactly at plan has no margin.
- When `04` answers, XL replaces L and every threshold is re-read against it. Until then, L is the ceiling we test to and nothing is promised above M.

## Scenarios

| # | Scenario | Path | Load shape | Pass line | Needs |
|---|---|---|---|---|---|
| **1** | **Occupancy on the gate spike** | `AccessScanService::scan` → `AccessDecisionService` | One capacity zone; ramp to 2× peak over 5 min, **hold 30 min** so logs accumulate; 10% unknown identifiers, 20% re-entries | Online decision p95 < 150 ms, p99 < 300 ms, **flat across the hold**; zero 5xx; `access_logs` rows = accepted scans | Now: a service-level harness over seeded data (no HTTP needed). HTTP via k6 once the endpoint is committed |
| 2 | Venue behind one NAT address | Public check-in routes, today's scanner | All requests from **one** source IP at production limiter settings; S and M peaks | Zero 429 at profile peak | Now — dev stack with the limiter restored to 180 |
| 3 | Sales spike | `POST /public/events/{id}/order`, complete, Stripe webhook | Existing `checkout-flow.js` spike with `CAPACITY`, plus a waitlist offer and an event edit mid-spike, plus signed Stripe test webhooks at the order rate | Order creation p95 < 1 s (`74`); zero oversell; sold-out as 422, never 5xx; zero 429 on webhooks | Now, with the k6 thresholds tightened to `74` |
| 4 | Occupancy snapshot under peak | Snapshot job + scenario 1 | Snapshot every 5–10 s during scenario 1 | Refresh finishes inside its interval; cached read < 100 ms; snapshot + delta equals the derived figure | ARZ-063 |
| 5 | Sync storm | `POST /api/devices/{id}/sync` (`71`) | M: 50 devices reconnect within 10 s, each holding 30 minutes of queued scans; 10% of each batch already synced; concurrent duplicate sends | Round-trip p95 < 2 s (`74`); every log exactly once; zero 5xx (L3); all devices settled within 5 min — **provisional**, no target exists in `74` | ARZ-101 |
| 6 | Badge render burst | Server render (ARZ-071) with fake printers (`37`) | Desks × 20 queued jobs at once; worst-case template: photo, Arabic name shaped at printer DPI, QR | Render p95 < 2 s (`74`); server throughput leaves printers the bottleneck | ARZ-071 |
| 7 | Command-center fan-out | Reverb channels (`71`) | 10 viewers per event × 5 events, during scenario 1 | Scan → dashboard < 2 s (`74`); counters broadcast at 1 Hz, not per scan; channel auth holds | ARZ-104, ARZ-170 |
| 8 | Event-day soak | Scenarios 1, 3 and 5 mixed | Profile M for 8 hours | No latency drift, no worker memory growth, no lock-wait growth | Before the first event on the new stack |

Scenario 1 is first because it is cheap and settles a design question: if code reading is right, the
snapshot cache in ARZ-063 moves from optimization to hard requirement, which `74` already flags as the
single most important thing to know before Phase 5.

## Decision: k6, not a second tool

| Option | Verdict |
|---|---|
| **k6** | **Chosen.** Two working scripts already exist with an oversell check. Arrival-rate executors model an open system — a spike does not slow down because the server did, so saturation shows as latency rather than hiding (coordinated omission). Thresholds fail the run, so it can run in CI. Plain JavaScript, readable by the frontend and device engineers. WebSocket support covers scenario 7 |
| Locust | Python; closed-model by default; no reuse of existing scripts |
| JMeter, Gatling | Heavier authoring for a team that already has k6 |
| Artillery | Viable; no advantage over what exists |

Trade-off: one k6 process per load generator. Profile L needs several generators — and deliberately
several source IPs, except in scenario 2 where one IP is the point. Distributed or hosted k6 costs are
`UNVERIFIED`.

## Environment

- **A production-like environment on the chosen hosting** (`84`, `85`), with production limiter settings, production Postgres version and data seeded at profile scale. ARZO has none today; this is the first dependency.
- **Never production during an event**, and never a live tenant. The event freeze in `123` applies to load generation too.
- **The dev stack is valid for relative comparisons only** — before/after a fix, scenario 1's harness, scenario 2's single-IP check. Never for absolute thresholds: a laptop is not the target.
- Seed data through an artisan command extending `dev:bootstrap` to profile scale — unnumbered, add to `136` when scheduled.

## Order of work

| Step | Run | When |
|---|---|---|
| 1 | Scenario 1 harness on the dev stack against today's `AccessScanService`; record per-scan time versus log count | Now |
| 2 | Scenario 2 single-IP check; if confirmed, key the public check-in limiter by list short id plus IP | Now |
| 3 | Fix L1; re-run scenario 1 to the same data; fix L3 with `INSERT … ON CONFLICT DO NOTHING` | Before the scan endpoint is committed |
| 4 | Scenario 3 with thresholds aligned to `74` | Before ARZO's first large on-sale |
| 5 | Scenarios 1 and 3 on the production-like environment | When hosting exists |
| 6 | Scenario 4 | With ARZ-063 |
| 7 | Scenarios 5 and 6 | Phase 4, before the first offline event |
| 8 | Scenario 7; scenario 8 soak | With ARZ-104; before the first event on the new stack |

After step 5, scenarios 1 and 3 run nightly and **warn** (`129`); a failure blocks any release into an
event freeze window.

## Open questions

- **What is the largest event ARZO intends to run?** Every threshold above is provisional until `04` answers. Owner: business.
- **Who owns and pays for the performance environment?** Hosting is undecided (`84`); cost `UNVERIFIED`.
- **Is a 5-minute sync-storm settle acceptable?** It decides how long figures stay "provisional until settled" after a reconnect. Leaning yes for M, to be revisited with `53`.
- **Does the per-IP limiter survive the device model?** Device keys (`40`) allow per-device limits; until then the public scanner needs its own limiter.

## Related

`74-performance.md` · `75-scalability.md` · `24-access-control.md` · `71-realtime-architecture.md` ·
`53-live-event-command-center.md` · `22-badge-design-printing.md` · `37-hardware-integration.md` ·
`84-infrastructure.md` · `123-release-strategy.md` · `126-disaster-recovery-plan.md` ·
`129-quality-gates.md` · `04-product-strategy.md`
