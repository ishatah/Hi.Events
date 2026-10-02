# Analytics Architecture

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep (commerce rollups) + New (log-derived attendance) · **Priority:** P2 · **Phase:** 2 onward
**Depends on:** `24-access-control.md`, `27-sessions-tracks.md`, `74-performance.md`
**Blocks:** `51-reporting.md`, `53-live-event-command-center.md`, `54-attendance-intelligence.md`, `55-event-intelligence.md`

---

## Current state — `PARTIAL`: sound rollups, no repair path, no attendance data

### Commerce rollups — `CONFIRMED`

| Table | Grain | Key columns |
|---|---|---|
| `event_statistics` | Event | `unique_views`, `total_views`, `sales_total_gross`, `total_tax`, `total_fee`, `products_sold`, `orders_created`, `orders_cancelled`, `total_refunded`, `attendees_registered`, `version` |
| `event_daily_statistics` | Event × date | As above minus `unique_views`; unique on `(event_id, date)` |
| `event_occurrence_statistics` | Occurrence | No view columns |
| `event_occurrence_daily_statistics` | Occurrence × date | No view columns |

No organizer- or account-level rollups exist; organizer reports aggregate these per request.

**Write path.** Order completion → `UpdateEventStatsListener` → `UpdateEventStatisticsJob` →
`EventStatisticsIncrementService::incrementForOrder` (`:47-78`), which updates all four tables plus
promo-code and product counters under **optimistic locking on `version`** with retries. Cancellation
decrements idempotently via `orders.statistics_decremented_at`. Refunds add to `total_refunded`
without reducing gross — the right call, since gross stays auditable.

This is a careful design. Two gaps in it:

| # | Gap | Evidence |
|---|---|---|
| A1 | **No repair or recalculation job.** If counters drift — a failed job, a manual DB fix, a bug — nothing rebuilds them from orders. | Search for recalculate/rebuild/resync; scheduler (`Console/Kernel.php:17-26`) |
| A2 | Refunds are dated to the **refund day** in the daily table, not the sale day | `EventStatisticsRefundService.php:112` — defensible, but must be stated in reports |

### Page views — three defects

| # | Defect | Evidence |
|---|---|---|
| A3 | **Views are lost for events with no orders**: the flush job increments `event_statistics.total_views` without creating the row if absent | `UpdateEventPageViewsJob.php:79-83` |
| A4 | Views are buffered and flushed in batches of 8; **fewer than 8 buffered views are never flushed** | `config/app.php:40` |
| A5 | `unique_views` is never written by application code — only by a dev seeder | `SeedDevDashboardDataCommand.php:215` |

Views are counted per event + IP per 5 minutes (`EventPageViewIncrementService.php:24-31`), with no
bot filtering. **Conversion rate — views to orders — is therefore not computable** for new events,
and is understated for all others.

### Attendance — schema only

`access_logs`, `session_attendance`, `zone_occupancy_snapshots` and
`access_point_throughput_snapshots` all exist (`c34f6a59`) with 0 rows. `access_logs` gained its
first writer in `e7228c1d` — `AccessScanService`, which derives occupancy per scan as entries minus
exits per credential rather than reading a counter, exercised by 11 integration tests. Its HTTP
endpoint was in progress and uncommitted at audit time, and **neither existing check-in path writes
`access_logs`** until ARZ-041. The snapshot tables and `session_attendance` have no writer. In
production terms, the only attendance data is still `attendee_check_ins`.

### Presentation

Charts use `@mantine/charts` (`AreaChart`, `Sparkline`). `recharts` appears in `package.json`
without direct imports; it is `@mantine/charts`' peer dependency, **not** dead weight.

### Realtime

None (`53`, `71`): four polling hooks at 2–5 s for orders, exports, occurrence generation and VAT,
and nothing pushed.

## The architecture: three data classes, three rules

| Class | Examples | Store | Rule |
|---|---|---|---|
| **Commerce** | Sales, tax, refunds, promo usage | Rollup tables (existing) | **Counters are acceptable** — writes happen online, inside transactions, exactly once per order state change |
| **Attendance and access** | Entries, occupancy, dwell, throughput | Append-only logs + time-bucketed snapshots | **Never counters.** Derive from logs; materialize snapshots for speed. Offline replay reorders and delays writes, which is what breaks counters. |
| **Attributes** | Company, job title, nationality, answers | `persons`, `question_answers` | Aggregate on read; **suppress small groups** |

The middle row is the architectural decision this plan has made repeatedly (`24`, `25`, `27`); it
is restated here because an analytics layer is where someone will be tempted to add a counter for
speed.

### Materializations for attendance

| Snapshot | Grain | Written by | Consumer |
|---|---|---|---|
| `zone_occupancy_snapshots` | Zone × capture time | Job every 5–10 s during an event (`25`) | `53`, `54` |
| `access_point_throughput_snapshots` | Access point × window | Job every 30–60 s (`20`) | `20`, `53`, `57` |
| Arrival curve | Event × minute | Derived on read from `access_logs (event_id, occurred_at)` — indexed | `54` |

Snapshots are caches: every figure they hold is reproducible from the logs, and the logs win when
they disagree.

### Late data

Offline devices submit hours late (`71`). Any attendance figure for a window is **provisional until
every device active in that window has synced past it**. Reports show a "settled" marker computed
from device sync cursors (`40`). A figure that silently changes the next morning erodes trust in
every figure.

## Freshness tiers

| Tier | Latency | Serves | Mechanism |
|---|---|---|---|
| Live | ≤ 10 s | Command center | Snapshots + broadcast (`71`) |
| Near-live | ≤ 5 min | Dashboards | Snapshots, rollups, 20–30 s report cache (exists) |
| Settled | After sync | Reports, post-event | Logs, once settled |

## When Postgres stops being enough — triggers, not dates

`05` and `53` both say "do not build an OLAP pipeline speculatively". Replace the open question
with measurable triggers:

| Trigger | Response |
|---|---|
| Snapshot job exceeds its own interval at peak | Partition `access_logs` by event (`74`) |
| Dashboard queries degrade checkout or scan latency | Read replica for analytics reads |
| Cross-event queries (`55`) exceed acceptable time on partitioned logs | A columnar store (DuckDB or ClickHouse) fed from settled logs |

`UNVERIFIED` which, if any, fires at ARZO's largest intended event — the size `04` asks for.
`125` load tests should measure against these triggers.

## Demographics

The scaffold's rule stands: **consented, structured collection only** — never inferred from names
or free-text answers. `persons` already holds `company`, `job_title`, `nationality`; answers to
select-type questions are aggregatable.

Any breakdown group smaller than **5** is suppressed in organizer and sponsor views. A table showing
one attendee of a given nationality identifies that person.

## Migration

| Step | Change |
|---|---|
| 1 | Fix A3–A5: create the statistics row on first view; flush partial batches on a schedule; write or drop `unique_views` |
| 2 | Repair command: rebuild all four rollup tables for one event from `orders` (A1) |
| 3 | Snapshot jobs for occupancy and throughput, active only during an event's window |
| 4 | "Settled" computation from device sync cursors |
| 5 | Small-group suppression in every attribute breakdown |

## Open questions

- **Which timezone is `event_daily_statistics.date` in** — event-local or UTC? `UNVERIFIED`; reports assume event-local.
- **Is IP-based view counting acceptable** under PDPL/GDPR? The IP is hashed into a cache key, not stored — probably fine, worth confirming (`65`).
- **Do snapshots run for every event or only those with on-site operations enabled?** Only the latter — most ticketing-only events have no access logs.

## Related

`51-reporting.md` · `53-live-event-command-center.md` · `54-attendance-intelligence.md` ·
`55-event-intelligence.md` · `20-queue-management.md` · `24-access-control.md` ·
`25-zones-and-permissions.md` · `71-realtime-architecture.md` · `74-performance.md` ·
`125-performance-testing-plan.md`
