# Background Jobs

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix + Extend · **Priority:** P1; J1–J3 and J5 fix now (ARZ-008 done; the rest unnumbered — add to `136` when scheduled) · **Phase:** fixes now; worker pools before Phase 4
**Depends on:** `84-infrastructure.md` (where workers run)
**Blocks:** `71-realtime-architecture.md`, `52-analytics.md` (snapshot jobs), `69-notifications-architecture.md`, `22-badge-design-printing.md`

---

## Current state — `PARTIAL`, ~55%

`CONFIRMED`: **27 jobs** in `app/Jobs`, plus 29 queued mailables (`69`). Retry policy is chosen job
by job: 13 declare `$tries`, 7 declare a backoff, 7 implement `failed()`, 2 set a timeout, 3 are
unique. Jobs that declare nothing inherit the worker flag — `--tries=3 --timeout=60` in the
all-in-one and dev (`supervisord.conf:40`, `start-dev.sh:215`).

### Inventory

| Job | Queue | Tries | Backoff (s) | Unique | Note |
|---|---|---|---|---|---|
| `ExecuteAccountDeletionJob` | default | 1 | — | — | Destructive; single attempt is right |
| `ProcessScheduledAccountDeletionsJob` | default | worker | — | — | Scheduled hourly |
| `EventSpamCheckJob` | default | 3 | 30, 120, 300 | until processing, 600 s, per event | Dispatched `afterCommit` |
| `SendEventEmailJob` | default | worker | — | — | Writes `outgoing_messages` (`69` N1, N2) |
| `SendMessagesJob` | default | worker | — | — | |
| `UpdateEventPageViewsJob` | default | worker | — | — | |
| `UpdateEventStatisticsJob` | default | 5 | 10 | 60 s, per order | Serializes the whole order |
| `DispatchEventWebhookJob` | **default** | worker | — | — | `49` W10 |
| `MessagePendingReviewJob` | default | 3 | — | — | |
| `SendScheduledMessagesJob` | default | worker | — | — | Scheduled every minute |
| `BulkCancelOccurrencesJob` | occurrences | 3 | 30 | — | |
| `GenerateOccurrencesJob` | occurrences | 3 | 30 | — | Timeout 120; batchable |
| `RefundOccurrenceOrdersJob` | occurrences | 3 | 60 | 3600 s | |
| `SendOccurrenceCancellationEmailJob` | occurrences | 3 | 30 | — | |
| `SendOrderDetailsEmailJob` | default | 3 | — | — | |
| `Dispatch{Attendee,CheckIn,Order,Product}WebhookJob` (4) | webhook-queue | worker | — | — | Each makes the receiver call synchronously (J1) |
| `DispatchOccurrenceWebhookJob` | **none — runs inline** | — | — | — | Not `ShouldQueue` (J2) |
| `ExportAnswersJob` | default | worker | — | — | Batchable; no timeout |
| `ValidateVatNumberJob` | default | 15 | method, `retryUntil` | — | The most deliberate policy in the codebase |
| `ProcessExpiredWaitlistOffersJob` | default | worker | — | — | Scheduled every minute |
| `SendWaitlist{Confirmation,Offer,OfferExpired}EmailJob` (3) | default | 3 | — | — | |
| `SecureCallWebhookJob` | **never queued** | config 3, dead | config exponential, dead | — | `dispatchSync()` (J1) |

Queues are named in `config/queue.php:16-18`; F13 is fixed — both names now default to
`webhook-queue` and `occurrences`.

### Topology per environment

| Environment | Connection | Worker | Queues | Evidence |
|---|---|---|---|---|
| Dev | **`sync`** — the worker started by `start-dev.sh` has nothing to consume | Unsupervised `exec -d` | default, webhook-queue, occurrences | `.env.example:50`; `start-dev.sh:215` |
| E2E | **`sync`** | none | — | `docker/e2e/.env:28` |
| All-in-one | redis | One supervisord process | default, webhook-queue, occurrences | `supervisord.conf:40`; all-in-one `.env.example:22` |
| Upstream SaaS | SQS on Lambda, `queue-concurrency: 5` | Vapor | `hievents-queue-prod`, `hievents-webhook-queue-prod` | `vapor.yml:16-20` |

The last row is **upstream Hi.Events' infrastructure** (`domain: api.hi.events`), deployed by a
workflow that reads upstream's secrets bucket (`deploy.yml:97-116`). ARZO's production queue
topology is `UNVERIFIED` — nothing in the repository describes it (`77` D2). Note that manifest lists
no `occurrences` queue: any SQS host must list every queue name the code uses.

### Operations tooling

Admin failed-jobs UI with list, search, retry, retry-all and delete (`api.php:565-569`;
`frontend/src/components/routes/admin/FailedJobs/index.tsx`). A scheduled closure warns when
`failed_jobs` is non-empty (`Kernel.php:21-26`). No Horizon, Pulse or Telescope (`composer.lock`).

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| J1 | **Webhook retries never run** — `dispatchSync()` makes `tries` and backoff dead config (`49` W1) | `WebhookDispatchService.php:210` | High |
| J2 | **`DispatchOccurrenceWebhookJob` is not `ShouldQueue`**, so `occurrence.cancelled` webhooks — each receiver call up to 3 s × 3 redirect hops — run **inside the organizer's cancel request** and inside `BulkCancelOccurrencesJob`. The listener's `onQueue()` is inert. | `DispatchOccurrenceWebhookJob.php:12`; `WebhookEventListener.php:53-57`; `CancelOccurrenceHandler.php:81` | Medium — one missing interface |
| J3 | redis `retry_after` is 60, equal to the worker `--timeout=60` and **below** `GenerateOccurrencesJob::$timeout = 120`: a job running past 60 s is released to a second worker while still running | `queue.php:84`; `supervisord.conf:40`; `GenerateOccurrencesJob.php:27` | Medium (self-host) — duplicate execution |
| J4 | Dev and E2E run `sync`, so **no environment short of production executes queued semantics** — serialization, retries, `afterCommit` ordering, uniqueness. ARZ-008 aligned queue *names*; the *connection* still diverges (F7). | `.env.example:50`; `docker/e2e/.env:28` | Medium |
| J5 | The failed-jobs monitor warns every five minutes while **any** failed job exists — forever — to stderr, which pages nobody (Sentry logs off by default) | `Kernel.php:21-26`; `.env.example:27,34` | Medium — alarm fatigue and no alarm at once |
| J6 | `failed_jobs.payload` holds serialized domain objects with personal data, searchable by `ilike` in the admin UI, with no retention; `UpdateEventStatisticsJob::failed()` also logs `order->toArray()` | `UpdateEventStatisticsJob.php:28-51`; `GetAllFailedJobsHandler.php:17-21` | Medium (`65`) |
| J7 | Stripe's inbound webhook is a **queued closure**: Stripe gets 204 regardless, and a closure that fails its worker retries is only logged, with the full payload | `StripeIncomingWebhookAction.php:21-39` | High — a lost payment event; `76` R4 |
| J8 | On a queued connection, `withoutOverlapping()` on `$schedule->job()` guards the **dispatch**, not the run; none of the three scheduled jobs is unique, so a run longer than a minute overlaps the next | `Kernel.php:17-19` | Low today; High once snapshot jobs exist |
| J9 | Uniqueness and scheduler mutexes need a shared atomic cache; the default `CACHE_DRIVER=file` makes them per node | `cache.php:18`; `.env.example:47` | Low single-node, High multi-node |
| J10 | 13 of 27 jobs have no test reference — all six webhook dispatch jobs, `SecureCallWebhookJob`, `UpdateEventStatisticsJob`, `ExportAnswersJob` | Search of `tests/` | Medium |
| J11 | Retry-all re-queues every failed job in one call, with no filter by queue or age | `RetryFailedJobHandler.php:23-31` | Low |

J1, J2, J3 and J5 are **fix now, independent of the roadmap** — each is a few lines.

## Decision: queue classes with a policy, enforced by a test

The scaffold proposed a base job. `CLAUDE.md` favours composition, and the real problem is not
inheritance but that **policy and routing are decided per job, and nothing checks either**. So:

```php
enum QueueClass: string
{
    case CRITICAL = 'critical';       // event-day: reconciliation follow-ups, deny-list, alerts
    case SNAPSHOT = 'snapshot';       // occupancy, throughput, settledness
    case NOTIFY = 'notify';           // notification fan-out and mail (69)
    case WEBHOOK = 'webhook-queue';
    case DEFAULT = 'default';         // commerce side effects
    case BULK = 'bulk';               // badge batches, exports, occurrences, backfills, retention
}
```

A trait applies the class's policy (queue, tries, backoff, timeout); jobs may tighten it, not
loosen it. An architecture test, in the style of ARZ-005, asserts that **every job declares a class**
and that **every class name appears in every worker manifest** the repository ships — the check that
would have caught F13 and the missing `occurrences` queue above.

| Class | Tries | Backoff (s) | Timeout (s) | Queue-wait target (provisional) |
|---|---|---|---|---|
| `critical` | 5 | 5, 15, 30, 60 | 30 | p95 < 5 s |
| `snapshot` | **1** — the next run supersedes a failed one | — | below its own interval | Finishes inside its interval |
| `notify` | per channel (`69`) | 30, 120, 600 | 60 | p95 < 60 s |
| `webhook-queue` | 5 | exponential (`49`) | 30 | p95 < 5 min |
| `default` | 3 | 10, 60, 300 | 60 | p95 < 60 s |
| `bulk` | 3 | 60, 300 | 900 | minutes |

`retry_after` on the queue connection must exceed the longest timeout in any class it carries (J3).

## Decision: the database row is the job's truth

Work that must not be lost — notification deliveries (`69`), webhook deliveries (`49`), badge print
jobs (`39`), device commands (`40`) — is a **row first**, with a status, and the queue message only
wakes a worker. A sweeper re-dispatches rows stuck in a pending state. This makes queue loss (Redis
restart, a dispatch that throws after commit — `76` R5) recoverable, and it is what makes
at-least-once delivery safe: handlers are idempotent on the row's status.

`badge_print_jobs` already follows this shape (`status`, `attempts`, `client_generated_id` unique)
and is pulled by print hosts, not by Laravel workers (`39`).

## Decision: event-day work runs on persistent workers

`critical` and `snapshot` need sub-minute cadence, warm connections and no cold starts. Reverb
already forces a persistent host (`05`, `84`); the event-day pools and a sub-minute scheduler run
there, not on Lambda. `bulk` may run anywhere, but **never on the same pool** as `critical`: a
10,000-badge pre-print batch must not delay a deny-list job.

| Pool | Classes | Runs on |
|---|---|---|
| A — event day | `critical`, `snapshot` | Persistent host next to Reverb |
| B — standard | `notify`, `webhook-queue`, `default` | Either |
| C — bulk | `bulk` | Either; scaled up before pre-print runs |

### Horizon

Horizon needs Redis queues and a persistent process — it does not work with SQS. If the persistent
host carries Redis queues, **adopt Horizon**: supervisors per pool, balancing, and queue wait and
throughput figures that feed `78` directly. If ARZO stays on SQS, the equivalent signals come from
the queue service's own metrics. Decide with `84`; the queue classes are the same either way.

## Worker sizing before Phase 4

`workers = Σ(arrival rate × mean service time) ÷ 0.6` target utilization. The inputs below are
**provisional**, from `74`'s working assumptions (one 10,000-attendee event, 20 zones, 20 access
points, 20 devices); every figure is `UNVERIFIED` until `125` measures service times.

| Load | Arrival | Service time (assumed) | Workers |
|---|---|---|---|
| Occupancy snapshot, one query per event per 10 s | 0.1/s | 0.5 s | 1 |
| Throughput snapshot, per 60 s | 0.02/s | 0.5 s | shares the above |
| Sync reconciliation follow-ups | ~1/s (20 devices, 15–30 s cadence) | 0.2 s | 1 |
| Badge pre-print, 10,000 badges in 45 min | 3.7/s | 2 s (`74` render target) | **13** |
| On-demand badge print, 6 desks | 0.1/s | 2 s | 1 |
| Notification fan-out, 10,000 recipients in 10 min | 17/s | 0.1 s | 3 |

Pool A: 2–3 workers. Pool C is the one to plan: pre-printing dominates, and it happens the day
before, so it can be scheduled rather than provisioned for peak.

## New scheduled jobs the plan adds

| Job | Cadence | Class | Owner | Guard |
|---|---|---|---|---|
| Occupancy snapshot | 10 s while any operated event is in `LIVE_OPS` | `snapshot` | `25`, `52` | Unique per event |
| Throughput snapshot | 60 s, same window | `snapshot` | `20`, `52` | Unique per event |
| Settledness (provisional → settled) | 5 min during and after an event | `snapshot` | `52` | Unique per event |
| Device silence detector | 30 s during an event | `critical` | `40` | Unique |
| Booth hold expiry | every minute | `default` | `35` | Unique |
| Readiness evaluation | at T-7d, T-24h, T-2h, hourly inside the last 24 h | `default` | `59` | Unique per event and review point |
| Delivery sweeper (`PENDING`/`DEFERRED` rows) | every minute | `notify` | `69` | Unique |
| Page-view partial flush (`52` A4) | 5 min | `default` | `52` | Unique |
| Statistics drift check; repair on demand | nightly | `bulk` | `52` A1 | Unique |
| Webhook log retention | daily | `bulk` | `49` W8 | Unique |
| Data retention and file purge | daily | `bulk` | `65`, `73` | Unique |
| `failed_jobs` retention (30 days proposed) | daily | `bulk` | `65` | Unique |
| Scheduler heartbeat | every minute | inline | `76` | — |

The scheduler runs on **one** instance, with a shared cache for its mutexes (J9).

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | J2 (`ShouldQueue`), J3 (`retry_after` above the longest timeout), J5 (monitor emits a gauge, alerts on increase — `78`) | Low — now |
| 2 | J1 with `49` step 1; J7 with `15` — verify before acknowledging, then queue | Low |
| 3 | `QueueClass`, policy trait, architecture test; migrate the 27 jobs | Low — mechanical |
| 4 | Dev on redis via `start-dev.sh` and `.env.example`; one E2E suite on a real queue (J4) | Medium — E2E gets slower |
| 5 | Pools A/B/C and Horizon (or SQS equivalents) on the host `84` chooses | Before Phase 4 |
| 6 | Scheduled jobs above, each landing with its owning domain | Phases 2–5 |

## Open questions

- **Where do ARZO's workers run?** Unknown today (`84`, `77` D2); it decides Horizon versus SQS.
- **Badge render engine and its CPU profile** (`22`) — the 2 s service time is `74`'s target, not a measurement, and it sizes Pool C.
- **Should `critical` jobs page someone on first failure?** Recommend yes during an event window, through `86`.
- **`failed_jobs` retention** — 30 days proposed; `65` owns the number.

## Related

`49-webhooks.md` · `52-analytics.md` · `69-notifications-architecture.md` · `71-realtime-architecture.md` ·
`76-reliability.md` · `78-observability.md` · `84-infrastructure.md` · `39-printer-integration.md` ·
`22-badge-design-printing.md` · `125-performance-testing-plan.md` · `02-current-state-audit.md` (F7, F13)
