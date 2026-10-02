# Performance Targets

**Status:** WRITTEN · **Audit date:** 2026-09-29 (refreshed; first written 2026-09-28) · **Baseline:** `develop` @ `e7228c1d`

---

## Honesty about what is known

`UNVERIFIED`: no load-test **results** exist in the repository, and this plan has **no stated target
event profile** — no attendee count, gate count, or arrival pattern. (Corrected 2026-09-29: the
first revision said no load-testing evidence existed at all. Upstream's k6 scripts do exist —
`misc/k6/checkout-flow.js` and `misc/k6/event-page.js` — with thresholds looser than the targets
below and nothing for the scan path. `125` builds on them.)

Every number below is therefore derived from **human-perception thresholds and operational
consequence**, not from measurement. They are targets to test against (`125`), not observed values.
Inventing measured-looking numbers would be worse than saying this.

The one input that would change everything: **the largest event ARZO intends to run.** Until that is
known, sizing is guesswork and the numbers should be treated as provisional.

## Why event load is unusual

Peak load is not driven by total attendees but by **arrival concentration**. Ten thousand attendees
arriving over eight hours is trivial; the same ten thousand arriving in thirty minutes through six
gates is the engineering problem.

That single fact is why offline-first (`71`) is a performance strategy as much as a reliability one —
devices absorb the spike and reconcile afterwards.

## Targets and reasoning

### Access decisions — the critical path

| Operation | Target p95 | Reasoning |
|---|---|---|
| Offline local decision | **< 50 ms** | An indexed local lookup. Above this the scanner feels laggy and operators start double-scanning. |
| Online decision | **< 150 ms** | Beyond roughly 200ms a visible hesitation appears at the gate, and a queue forms behind it. |
| Identifier resolution | < 30 ms | Indexed hash lookup |
| Grant materialization at issue | < 500 ms | A person is standing at a desk |

The offline target is stricter than the online one deliberately: a local SQLite lookup has no excuse
to be slow, and offline is the mode that must feel *better*, not worse.

### Badge printing

| Operation | Target | Reasoning |
|---|---|---|
| Render to PDF, p95 | < 2 s | Someone is waiting |
| Render to physical badge | < 10 s | Printer mechanics dominate; software must not add to it |
| Queue drain under burst | 60+ badges/hour/printer | A realistic desk rate; drives how many printers per event (`102`) |

### Checkout — existing, protect it

| Operation | Target | Note |
|---|---|---|
| Product page TTFB | < 500 ms | SSR-rendered; only 4 of 88 routes have loaders |
| Order creation | < 1 s | Serialized by an advisory lock per event — **this is the contention point under a sales spike** |
| Payment intent | < 2 s | Stripe-bound |

The advisory lock is correct for consistency and is the natural bottleneck when a popular event opens
sales. Worth load-testing specifically (`125`).

### Sync

| Operation | Target | Reasoning |
|---|---|---|
| Delta sync round-trip | < 2 s | Must complete inside a 15s cadence on poor venue wifi |
| Full roster sync, 10k attendees | < 60 s | Pre-event staging, never at the door (`103`) |
| Deny-list propagation, online | < 30 s p95 | Revocation urgency (R8) |

### Command center

| Operation | Target | Reasoning |
|---|---|---|
| Scan → dashboard | < 2 s | Perceived as live |
| Occupancy read, cached | < 100 ms | Dashboard refresh |
| Occupancy snapshot refresh | 5–10 s | Balance freshness against query cost |

**Update 2026-09-29 — code reading now predicts the answer.** The first implementation of the scan
path landed in `e7228c1d`, and it computes occupancy the expensive way on the critical path:

```
AccessScanService::scan()                         -- every scan with a zone (line 90)
  -> occupancyFor(zone, event)                    -- lines 174-186
       SELECT credential_id, SUM(±1) ... GROUP BY credential_id  -- all granted logs in the zone
       ->get()                                     -- every group transferred to PHP
       ->filter(net_inside > 0)->count()           -- counted in PHP
```

It runs **whether or not any rule enforces capacity**, and its cost grows with every credential that
has entered the zone — so across an arrival spike the total work is roughly quadratic in arrivals.
For a 10,000-person zone at 600 scans a minute it cannot meet the < 150 ms online target.

The snapshot cache is therefore a **hard requirement**, not an optimization. Three changes, in
order of value: compute occupancy only when a matching grant enforces capacity and the zone has one;
aggregate in SQL instead of transferring rows; and read `zone_occupancy_snapshots` on the hot path
with the live aggregate as the fallback. Load-testing this path is scenario 1 in `125`, and now its
purpose is to confirm a prediction rather than to discover a risk.

### API

| Operation | Target |
|---|---|
| Read endpoint p95 | < 300 ms |
| Write endpoint p95 | < 500 ms |
| Export initiation | < 1 s (async thereafter) |

## Concurrency assumptions — provisional

Stated so they can be corrected rather than left implicit:

| Dimension | Working assumption | Confidence |
|---|---|---|
| Concurrent scanners per event | 20 | Low |
| Scans per minute at peak | 600 (6 gates × 100) | Low |
| Concurrent events | 5 | Low |
| Attendees per large event | 10,000 | Low |
| Concurrent checkout sessions at launch | 500 | Low |

All **low confidence**. They exist to be replaced by real figures from `04`.

**Inconsistency noted by `102`:** 20 scanners producing 600 scans a minute implies a 2-second service
time per scanner — optimistic for a door with bag checks or photo verification. At 6 seconds the same
peak needs about 75 lanes. Similarly, "60+ badges per hour per printer" (a desk rate including
capture and conversation) sits far below "under 10 s per badge" (the machine). Both pairs measure
different things; neither is wrong, but sizing must use `102`'s formula —
positions = ⌈λ · s / 60 / ρ⌉ for arrival rate λ per minute, service time s in seconds, utilization
ρ — with **measured** service times from the pilot, not these placeholders.

## Known performance characteristics

From the audit, `CONFIRMED`:

**Good:**
- Route-level code splitting across all 88 routes via `async lazy()`
- Runtime env injection, so one image serves all environments
- Statistics rollup tables avoid expensive aggregates on read
- `incrementEach`/`decrementEach` do atomic multi-column updates with no read-modify-write race

**Concerns:**
- `ssrManifest` is loaded, passed to `render()`, and **silently ignored** — so no preload hints are emitted for the lazy chunks a page needs. Real, measurable LCP left on the table.
- The `.ssr-loader` white overlay hides SSR output until hydration, negating the perceived-performance benefit of SSR on 84 of 88 routes.
- No `manualChunks` strategy, so heavy shared dependencies (Mantine ×13, TipTap ×9, Recharts) land wherever Rollup puts them with no cache-friendly vendor boundary.
- `@react-pdf/renderer` is a dead production dependency in the runtime image.
- Sparse FK constraints mean the query planner has less information than it could.

Each of these is a cheap win and is recorded in `121`.

## Approach

1. **Establish the target event profile** (`04`). Without it, everything above is provisional.
2. **Load-test the two critical paths first:** access decision under scan burst, and checkout under sales spike.
3. **Measure before optimizing.** The SSR preload and chunking issues are known and cheap; the rest should wait for data.
4. **Budget per surface** — a bundle-size and latency budget enforced in CI (`129`), so regressions are caught rather than discovered.

## Related

`75-scalability.md` · `125-performance-testing-plan.md` · `24-access-control.md` ·
`71-realtime-architecture.md` · `53-live-event-command-center.md` · `121-technical-debt.md`
