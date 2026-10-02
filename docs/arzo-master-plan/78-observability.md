# Observability

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Extend · **Priority:** P1; O1–O3 fix now; event-day metrics with Phase 4 — unnumbered, add to `136` · **Phase:** foundation now; device metrics Phase 4
**Depends on:** `70-background-jobs.md`, `40-device-management.md`, `84-infrastructure.md`
**Blocks:** `53-live-event-command-center.md`, `86-monitoring.md`, `76-reliability.md`

---

## Current state — `PARTIAL`, ~35%: errors covered, operations not

`02` scored this 50% on "Sentry backend + frontend". Two corrections: the "frontend" Sentry is the
**Node SSR server**, not the browser; and the backend sends personal data despite a privacy-safe flag.

| Fact | Evidence |
|---|---|
| Backend: `sentry/sentry-laravel` ^4.13; `sample_rate` 1.0; **`traces_sample_rate` and `profiles_sample_rate` null** unless set; `send_default_pii` false; SQL bindings off; `/up` ignored | `config/sentry.php:11-48`; `.env.example:37` sets traces to 0 |
| **Every reported exception attaches the user's id, email, full name and IP** to the Sentry scope, plus impersonation tags | `Exceptions/Handler.php:44-70` |
| SSR: `@sentry/node` ^10.73, traces default 0, user info, cookies, headers and bodies **not** collected — a careful setup | `frontend/instrument.mjs:23-53`; `package.json:45` |
| **No browser SDK**: no `@sentry/react` or `@sentry/browser` — client-side errors, including the check-in scanner's, are reported nowhere | `frontend/package.json` |
| Logs: `LOG_CHANNEL=stderr`, `LOG_LEVEL=debug`, line format unless `LOG_STDERR_FORMATTER` is set; a `sentry_logs` channel exists, off by default | `.env.example:27-34`; `config/logging.php:97-106,121-124` |
| No request or correlation id across SSR → API → job | Search for `request_id`, `X-Request-Id`, `withContext` |
| **No metrics**: no Prometheus client, OpenTelemetry, StatsD, Horizon, Pulse or Telescope | `composer.lock`, `package.json` |
| The only operational signal: a failed-jobs count logged every 5 min (`70` J5) | `Console/Kernel.php:21-26` |
| The only operator-facing metric: a 5-minute throughput computed in the browser from 20 recent check-ins (`53`, `51` R7) | `StatsTab` |
| Admin system page: version, PHP, Laravel, environment, debug flag | `GetSystemInfoAction.php:17-27` |
| Which Sentry project, if any, receives ARZO's errors | `UNVERIFIED` — the only deploy configuration is upstream's (`77` D2) |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| O1 | **Personal data sent to Sentry on every exception**, overriding `send_default_pii = false`; `64` lists that flag as a privacy default | `Handler.php:54-60` | Medium — needs a processor agreement and a residency answer (`65`) |
| O2 | **Browser errors are invisible** — a scanner that throws at the door leaves no trace | No browser SDK | High for event day |
| O3 | Tracing off by default; offline sync bugs will be distributed and undebuggable without traces | `sentry.php:30` | Medium |
| O4 | No correlation id | Search | Medium |
| O5 | Personal data in logs: `UpdateEventStatisticsJob::failed()` logs the whole order; the Stripe webhook failure path logs the whole payload | `UpdateEventStatisticsJob.php:46-51`; `StripeIncomingWebhookAction.php:26-30` | Medium |
| O6 | `LOG_LEVEL=debug` is the documented default | `.env.example:29` | Low — cost, and more personal data in logs |

O1, O2 and O5 are **fix now**: keep the user id and impersonation tags, drop email, name and IP;
add the browser SDK with the same data-collection restrictions the SSR setup already uses.

## Decision: four signals, each with one job

| Signal | Tool | Answers |
|---|---|---|
| Errors | Sentry — backend, SSR, **browser**, and the native apps (`94`) | What broke, for whom (by id), since which release |
| Traces | Sentry performance, sampled | Why this request or sync was slow |
| Metrics | Prometheus-compatible time series + Grafana | Is the system healthy **now**; is it trending wrong |
| Logs | Structured JSON on stderr, shipped by the host | What exactly happened in this request |

Most event-day **business** figures — scans, occupancy, device state, print queues — are already
rows in Postgres (`access_logs`, `devices`, `badge_print_jobs`, the snapshot tables). The command
center computes them from there (`53`). Observability needs **platform** metrics plus a small set of
business gauges exported from those same tables, so that alerts and on-call dashboards live in one
place.

## Decision: Prometheus-compatible metrics and Grafana

| Criterion | Weight | Why it decides |
|---|---|---|
| Alerting on labelled time series (per queue, per access point) | High | The alert list in `53` and `86` needs it |
| Data stays inside the perimeter | High | The same argument that chose Reverb (`71`) — gauges labelled by event and access point are operational data about ARZO's clients |
| No per-series pricing | Medium | Event labels create series per event |
| Operational cost | Medium | ARZO's team size is `UNVERIFIED` (`05`) |

**Recommendation:** Prometheus-compatible metrics with Grafana, self-hosted on the persistent host
that Reverb requires (`84`), or a hosted Grafana stack if ARZO lacks the capacity to run it — the
instrumentation is identical, so the choice can change later. Do not make Sentry the metrics store:
it is the error and trace tool, and alerting on queue depth is a different job.

### Collection without touching the hot path

- A small `Metrics` interface (counter, gauge, histogram). The Prometheus adapter stores in Redis so
  all PHP workers share it, exposed at `/metrics` on an internal-only route; a no-op adapter on hosts
  without a scraper.
- **Business gauges are computed, not emitted per scan.** A `snapshot`-class job (`70`) every 15 s
  during a live event reads the tables and sets the gauges. Scans pay nothing for being observed.
- Queue figures come from Horizon if the host uses Redis queues, or from the queue service
  otherwise (`70`).
- **Cardinality rule:** allowed labels are `event_id` (operated events only), `access_point_id`,
  `zone_id`, `device_id` (tens per event), `queue`, `job`, templated `route`, `status_class`,
  `channel`. **Never** person, credential, attendee, order or email.

## Metrics catalogue

Thresholds are **provisional**, derived from `74` and `76`.

| Metric | Type · labels | Source | Alert when |
|---|---|---|---|
| `arzo_scans_total` | counter · event, access_point, result | `access_logs`, via the gauge job | Denial share > 15% over 5 min at one access point (`53`) |
| `arzo_scan_rate` | gauge · event, access_point | Same | Zero for 5 min at an open access point during doors |
| `arzo_access_decision_seconds` | histogram · mode (online / offline) | Server timer in the scan path; device p95 in the heartbeat | Online p95 > 150 ms; offline p95 > 50 ms |
| `arzo_sync_lag_seconds` | gauge · event, device | `devices.last_sync_cursor` age | Any active device > 300 s; fleet p95 > 60 s |
| `arzo_unsynced_logs` | gauge · device | Heartbeat `unsynced_count` (`40`) | > 500, or rising for 10 min |
| `arzo_devices_online_ratio` | gauge · event, device_type | `last_seen_at` < 90 s among assigned `ACTIVE` devices | < 0.9 during doors |
| `arzo_device_clock_skew_seconds` | gauge · device | Heartbeat clock vs server | > 30 s (`71`) |
| `arzo_deny_list_propagation_seconds` | histogram · event | Revocation time to device acknowledgement | p95 > 30 s |
| `arzo_print_queue_depth` | gauge · event, printer | `badge_print_jobs` `QUEUED`/`SENT` | > 10, or oldest > 120 s |
| `arzo_print_failures_total` | counter · event, printer | `badge_print_jobs` `FAILED` | Any, with a queue behind it |
| `arzo_queue_depth`, `arzo_queue_oldest_job_seconds` | gauge · queue | Horizon or queue service | `critical` > 30 s; `notify` > 5 min; `bulk` > 30 min |
| `arzo_job_failures_total` | counter · queue, job | Queue events | Rate > 1% over 15 min; **any** failure in `critical` |
| `arzo_job_duration_seconds` | histogram · queue, job | Queue events | Snapshot job longer than its interval |
| `arzo_scheduler_heartbeat_age_seconds` | gauge | `76` heartbeat | > 180 s |
| `arzo_http_request_seconds` | histogram · route, method, status_class | Middleware | p95 over `74` targets; 5xx > 1% over 5 min |
| `arzo_checkout_lock_wait_seconds` | histogram | Timer around the advisory lock (`75` K3) | p95 > 500 ms |
| `arzo_notification_deliveries_total` | counter · channel, status | `notification_deliveries` (`69`) | `FAILED` share > 5% per channel |
| `arzo_webhook_deliveries_total` | counter · status | `webhook_deliveries` (`49`) | Final failures > 1% |
| `arzo_realtime_connections`, `arzo_broadcast_lag_seconds` | gauge, histogram | Reverb (`71`) | Lag p95 > 2 s |
| Database connections, replication lag, slow queries | Host exporter | Provider | Connections > 80% of max (`75`) |

## Traces

- Backend: sampled with a sampler, not a flat rate — **100%** of device sync, heartbeat and scan
  requests during an operated event (a few requests per second, `75`), 5% of everything else, none
  for `/up`.
- Propagate `sentry-trace` and `baggage` from SSR to the API, and have devices send a trace header on
  every sync, so a device's bad sync and the server spans that handled it are one trace.
- Keep SQL bindings out of spans (the current default).

## Logs

JSON formatter in production (`LOG_STDERR_FORMATTER`), level `info`. A middleware accepts or
generates `X-Request-Id`, adds it to the log context, the Sentry scope and the response; jobs carry
their job id and the originating request id. **Ids in logs, never names or emails** — O5 is the
first cleanup.

## Who watches

Observability without an operator is decoration. During an operated event, the ops room watches the
command center (`53`) — business signals — and the engineer on call (`76`) watches the platform
dashboard. **Every alert names an owner and links a runbook step** (`107`); an alert without an owner
is deleted, not muted. Routing and escalation are `86`.

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | O1: scope carries id and impersonation only; O5 log cleanup; O6 default `info` | Low — **now** |
| 2 | O2: browser Sentry SDK with restricted data collection; release tagging | Low — **now** |
| 3 | O3: trace sampler; header propagation SSR → API; request-id middleware (O4) | Low |
| 4 | `Metrics` interface, Redis-backed Prometheus adapter, `/metrics`; HTTP, queue, scheduler metrics | Medium — needs the host (`84`) |
| 5 | Grafana dashboards and alert rules for the `76` SLOs | Before the first operated event |
| 6 | Device, sync, print and deny-list metrics from heartbeats and the gauge job | Phase 4, with `40` |

## Open questions

- **Self-hosted or hosted Grafana stack?** Decided by ARZO's operations capacity and `84`.
- **Sentry region and processor agreement** for attendee-adjacent errors — `65`.
- **Retention of metrics and traces** — 30 days of raw metrics and 13 months of downsampled event summaries is the proposal.
- **Do clients get read-only dashboards for their own events?** Plausible for enterprise (`101`); needs per-tenant scoping of the metrics store, which the label rule above makes possible.

## Related

`53-live-event-command-center.md` · `86-monitoring.md` · `76-reliability.md` · `70-background-jobs.md` ·
`40-device-management.md` · `71-realtime-architecture.md` · `74-performance.md` · `75-scalability.md` ·
`64-security.md` · `65-privacy-gdpr.md` · `84-infrastructure.md` · `107-event-day-runbook.md`
