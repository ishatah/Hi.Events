# Reliability

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix + Process + Business commitment (on-call, SLA) · **Priority:** P1; R1–R4 fix now; R7 is `77` D1 (P0) — unnumbered, add to `136` · **Phase:** probes and heartbeats now; SLOs before the first operated event
**Depends on:** `71-realtime-architecture.md`, `78-observability.md`, `70-background-jobs.md`
**Blocks:** `77-disaster-recovery.md`, `86-monitoring.md`, `127-production-readiness.md`

---

## Current state — `PARTIAL`, ~25%

A ticketing outage loses revenue. A gate outage puts a crowd behind a door. The system today can
tell neither from a healthy afternoon, because nothing it exposes can report a failure.

| Fact | Evidence |
|---|---|
| `/up` returns a static `{"status":"ok"}` — no database, Redis, queue, scheduler or storage check | `routes/web.php:20-22` |
| `/up` bypasses maintenance mode and is excluded from Sentry tracing | `PreventRequestsDuringMaintenance.php:16`; `config/sentry.php:45-48` |
| Only the E2E stack probes `/up` | `docker-compose.e2e.yml:24` |
| The all-in-one **app** container has no healthcheck; only its Postgres and Redis do; the image has no `HEALTHCHECK` | `docker/all-in-one/docker-compose.yml:2-58,63,74`; `Dockerfile.all-in-one` |
| Startup aborts on a failed migration — good | `startup.sh:5-12` |
| supervisord restarts dead processes (`autorestart=true`); that proves a process exists, not that it works | `supervisord.conf` |
| Dev worker and scheduler are unsupervised `exec -d` processes | `start-dev.sh:215,223` |
| No worker or scheduler heartbeat; the only signal is a failed-jobs count in a log line (`70` J5) | `Kernel.php:15-27` |
| Webhook retries never run (`49` W1); job retry policy is per job (`70`) | `WebhookDispatchService.php:210` |
| Stripe's inbound webhook answers 204 before verification or processing, via a queued closure | `StripeIncomingWebhookAction.php:21-39` |
| A `failover` mailer exists but is not the default | `config/mail.php:17,83-89` |
| Offline operation — the primary reliability mechanism — does not exist yet | `71` |
| On-call rotation, SLA commitments, status page | `MISSING` — no evidence in the repository; `UNVERIFIED` as business arrangements |
| ARZO's production host | `UNVERIFIED` — the deploy workflow and `vapor.yml` are upstream Hi.Events' (`77` D2) |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| R1 | No readiness signal anywhere: with the database down, every probe still reports healthy | `web.php:20-22` | High on any orchestrated host |
| R2 | The all-in-one app container has no healthcheck at all | `docker-compose.yml:2-58` | Medium |
| R3 | A dead queue worker or scheduler is **silent** — scheduled messages, waitlist expiry and statistics stop without a trace | No heartbeat | High on event day |
| R4 | **Stripe webhook acknowledged before it is processed.** A handler that fails after its retries is only logged; Stripe, having seen 204, does not redeliver | `StripeIncomingWebhookAction.php:21-39` | High — payment state; see `15`, `70` J7 |
| R5 | Side effects are dispatched `afterCommit`; if the queue is unreachable at that moment the business write has committed and the side effect is lost, with nothing to replay it | Framework behaviour; `UNVERIFIED` in this codebase — test in `79` | Medium |
| R6 | The failover mailer is unused, so a provider outage fails mail rather than rerouting | `mail.php:17` | Low |
| R7 | **ARZO's code and plan exist on one workstation** | `77` D1 | Critical |

R1–R4 are **fix now, independent of the roadmap**. R7 is the first item of `77`.

## Decision: keep `/up` static; add readiness and a deep check

The scaffold said to replace the static check. Half right. A **liveness** probe that checks the
database makes an orchestrator restart healthy app nodes whenever the database blips — a restart
storm on top of an outage. Liveness should stay static. What is missing is **readiness** and a
diagnostic view.

| Endpoint | Checks | Fails with | Consumer |
|---|---|---|---|
| `/up` (keep) | Process answers | Nothing — it cannot fail | Container liveness |
| `/health/ready` | `SELECT 1` (1 s timeout), Redis `PING`, cache write/read | 503 | Load balancer, container readiness |
| `/health/deep` — internal token or `SUPERADMIN` | Queue depth and oldest-job age per class; scheduler heartbeat age; failed jobs in the last hour; storage reachable; mail transport; Reverb reachable; migrations current | JSON detail, 200 always | `78` scrape, the admin system page |

Two heartbeats make the silent failures loud:

- **Scheduler:** a closure every minute writes the time to the shared cache; `deep` reports its age.
- **Workers:** a canary job per queue class every minute records enqueue-to-run latency. It proves
  a worker is alive **and** measures queue wait, which a process check cannot.

## Decision: event-day availability is measured at the door

Server uptime is the wrong measure for an event. The measure is whether every person presented at
a door gets a decision — which offline-first (`71`) makes independent of the server. Redundancy is
the second line; offline is the first.

### Event-day SLOs — during an operated event's `LIVE_OPS` window (`56`)

All **provisional**, to be tuned after the pilot.

| SLI | Objective |
|---|---|
| Scans that receive a decision within 1 s, online or offline | **100%** — any miss is an incident |
| Online decision latency | p95 < 150 ms (`74`) |
| Active devices synced within 60 s | ≥ 95%; none silent > 5 min without an alert |
| Deny-list propagation, online | p95 < 30 s (`74`) |
| Badge job to printed | p95 < 10 s; no printer queue stalled > 2 min unalerted |
| Scan to command center | p95 < 2 s (`53`) |
| Checkout and on-site payments (API) | 99.9% of requests without 5xx over the window |

### Normal operation SLOs — per calendar month

| SLI | Objective |
|---|---|
| Public event pages and checkout availability | 99.9% (~43 min/month) |
| Checkout requests without 5xx | 99.5% |
| Transactional email handed to the provider | 99% within 5 min |
| Webhook delivered or finally failed | 99% within 15 min (after `49` step 1) |
| Job failure rate, per queue class | < 0.5% per day |

### Error budget and change freeze

When an ARZO-operated event is between T-24h and the end of `LIVE_OPS`, **no deploys** except a
hotfix approved by the event's ops lead. The monolith deploys globally, so a freeze is global; for
SaaS customers ARZO does not operate, deploys avoid their published on-sale times where known. An
exhausted monthly budget stops feature deploys until the cause is fixed.

## Failure modes

| Failure | Today | Target |
|---|---|---|
| Venue network down | Check-in stops | Devices decide locally; emergency mode after 30 min (`71`) |
| Backend unreachable | All check-in and checkout stop | Doors continue; local print host prints from cache (`39`); checkout down, stated honestly |
| Postgres down | Everything online fails; probes say healthy (R1) | Readiness fails; traffic drains; doors continue offline |
| Redis down | All-in-one queue stops; `afterCommit` dispatches lost (R5) | Outbox rows replayed by sweepers (`70`) |
| Worker or scheduler dead | Silent (R3) | Heartbeat alert within 3 min |
| Mail provider down | Retries without backoff, then `failed_jobs` | Failover mailer (R6); delivery log shows the gap (`69`) |
| S3 down | Uploads fail; images break | Same; uploads retried by the client |
| Reverb down | — | Command center shows its own staleness (`53`) |
| Stripe down | Checkout fails | Same — shown as the provider's outage |
| Printer or device fails | — | Spare enrolled in advance; readiness check (`59`) |

## Brownout switches

Load that can be shed during an event peak, each a runtime setting an operator can flip without a
deploy: page-view counting (`52`), export generation, the `bulk` pool, non-critical notification
categories, webhook delivery (queued, not dropped). Each switch logs who flipped it (`67`).

## People

Reliability engineering without an on-call is aspirational. Minimum before the first operated
event: one engineer on call for every ARZO-operated event window, reachable within five minutes, with
the runbook (`107`) and authority to roll back. Outside events, business hours until `101` commits
to more. This is a staffing commitment, not software — `106` owns it.

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | R4: verify the Stripe signature synchronously, then queue; return 400 on a bad signature | Low — **now**, with `15` |
| 2 | `/health/ready`, `/health/deep`, scheduler heartbeat, per-class canary jobs (R1, R3) | Low — now |
| 3 | Healthcheck in the all-in-one compose file and image (R2) | Low — now |
| 4 | Outbox sweepers for deliveries (`69`, `49`) (R5) | Medium |
| 5 | Failover mailer as default where two providers are configured (R6) | Low |
| 6 | SLO dashboards and alerts (`78`, `86`); brownout switches | Before the first operated event |
| 7 | Change-freeze policy adopted (`106`); on-call staffed | Business — before the first operated event |

## Open questions

- **What availability does ARZO promise clients?** `101` — the monthly SLOs above are a proposal, not an offer.
- **Who is on call, and who can declare island mode (`77`)?** `106`.
- **Status page** — public, per event, or none? Recommend one for SaaS; for operated events the ops room is the status page.
- **Is a second region worth it?** Not before offline-first exists: devices protect the door better than a standby region does, at a fraction of the cost.

## Related

`71-realtime-architecture.md` · `77-disaster-recovery.md` · `78-observability.md` · `70-background-jobs.md` ·
`86-monitoring.md` · `53-live-event-command-center.md` · `59-event-readiness.md` · `106-event-operating-model.md` ·
`107-event-day-runbook.md` · `101-enterprise.md` · `127-production-readiness.md` · `15-payments-invoicing-vat.md`
