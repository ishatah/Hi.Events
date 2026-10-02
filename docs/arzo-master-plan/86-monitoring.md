# Monitoring and Alerting

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New · **Priority:** P1 — platform alerts before the first ARZO event on the platform; event-day alerts with ARZ-123 and ARZ-170 · **Phase:** platform now; event-day Phases 4–5
**Depends on:** `78-observability.md`, `84-infrastructure.md`, `53-live-event-command-center.md`, `60-incident-management.md`
**Blocks:** `107-event-day-runbook.md`, `127-production-readiness.md`, `131-event-readiness-checklist.md`

---

What is watched, who is told, and what they may do about it. `78` produces the signals; this document
turns them into alerts with owners. An alert without a watcher is decoration (`53`).

## Current state — `MISSING` for ARZO, ~15% in the code

There is no ARZO environment to monitor (`84`). What the code carries:

| Fact | Evidence |
|---|---|
| Backend exceptions go to Sentry with user, IP and impersonation scope — **when a DSN is set**; `.env.example` leaves it empty | `app/Exceptions/Handler.php:54-70`; `backend/.env.example:34` |
| The Node SSR server reports to Sentry with bodies, headers, cookies and user info excluded | `frontend/instrument.mjs:23-53` |
| **No browser SDK**: only `@sentry/node` is installed and nothing in `src` imports Sentry, so errors in the browser — checkout, the scanner — are never reported | `frontend/package.json`; search |
| Tracing off; Sentry logs off; logs to stderr | `.env.example:29,36,39` |
| "Failed-jobs monitor": every 5 minutes, **a log line** if `failed_jobs` is non-empty. Nobody is notified | `app/Console/Kernel.php:21-26` |
| The admin failed-jobs UI can list and retry | `Http/Actions/Admin/FailedJobs/*` |
| `/up` reports healthy with the database down | `routes/web.php:20-22` (`76`) |
| Uptime checks, metrics, dashboards, paging, on-call | **`MISSING`** in the repository; upstream may run external checks — `UNVERIFIED` |
| Event-day signals | None yet: no device heartbeat, no snapshot writers (`53`) |

`02` and `78` describe Sentry as covering "backend and frontend". The frontend part is the SSR
server only.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| M1 | No ARZO monitoring — follows from `84` | Above | **P0** with `84` |
| M2 | Browser errors are invisible; a checkout or scanner failure on a phone leaves no trace | `package.json`; `instrument.mjs` | **Medium — fix now**: add the browser SDK with the SSR privacy settings |
| M3 | Failed jobs produce a log line nobody reads | `Kernel.php:21-26` | Medium |
| M4 | Health check cannot see dependencies | `/up` | Medium (`76`) |
| M5 | Tracing off, so distributed sync problems will be undebuggable | `.env.example:39` | Low until Phase 4 (`78`) |

## Decision: two catalogues, two audiences

| | Platform alerts | Event-day alerts |
|---|---|---|
| Question | Is the system healthy? | Are people getting in, and is the equipment working? |
| Audience | Engineering on call | The event's ops room and floor supervisors (`53`, `57`) |
| Lives in | An external monitoring tool | The application — they are product features of the command center |
| Why there | They must fire **when the application is down** | They need domain data: zones, devices, rosters |

A few conditions cross over; the routing rule below handles them.

## Decision: every alert has a symptom, an owner, a severity and a runbook

Alert on what attendees and operators feel (`53`). An alert that ships without a runbook entry
(`107` for event-day, engineering runbooks and `126` for platform) is not shipped.

| Severity | Meaning | Acknowledge within |
|---|---|---|
| **Critical** | Attendee-facing outage or data at risk | 5 min, at any hour inside an event window |
| **High** | A door, desk or sale path degraded | 10 min in event windows; next business hour otherwise |
| **Medium** | Degraded with a workaround | Same shift |
| Info | Dashboard only | — |

High and Medium match `53`'s table; Critical is added for platform outages. Alerts **propose**
incidents; a person confirms and sets the SEV1–4 (`60`).

## Platform catalogue

Thresholds are starting guesses, `UNVERIFIED` — tune after the first events.

| Condition | Severity |
|---|---|
| Synthetic check on the readiness endpoint and a public event page fails twice in a row | Critical |
| 5xx rate above 2% for 5 minutes | High |
| Payment failures or Stripe webhook failures above baseline — including signature rejections, once the handler verifies before returning (`15`) | High |
| Oldest job on `default` or `webhook-queue` older than 5 minutes — tickets and confirmations are late | High |
| Scheduler heartbeat missing for 3 minutes — scheduled messages and waitlist expiry stop | High |
| `failed_jobs` grew (replaces M3's log line) | Medium |
| Postgres connections or storage above 80%; backup or point-in-time recovery failure | High |
| Redis memory near limit, or evictions | Medium |
| Reverb down, or connections drop to zero during an event window | High |
| Sync endpoint error rate above 5% during an event window (Phase 4) | High |
| Webhook deliveries failing after retries (once `49` W1 is fixed) | Medium |
| Mail bounce or complaint rate spike | Medium |
| TLS certificate expires within 14 days | Medium |
| New error type in the current release | Info |

## Event-day catalogue

`53`'s table, unchanged, plus the technical conditions it depends on:

| Condition | Severity | Source |
|---|---|---|
| Queue above threshold at any access point | High | `53`, `20` |
| Zone at or above capacity | High | `53`, `25` |
| Printer offline or out of stock **with a queue** | High | `53`, `39` |
| Device offline more than N minutes | Medium | `53`, `40` |
| Denial-rate spike | Medium | `53`, `24` |
| Session at capacity with people waiting | Medium | `53`, `27` |
| Staff position unfilled at an open gate | Medium | `53`, `57` |
| Device entered emergency mode (`71`) | High | Added |
| Device data older than 15 minutes (`59`'s sync threshold) | Medium | Added |
| Clock skew beyond threshold | Medium | Added (`40`) |
| Kiosk took itself out of service (`19`) | High | Added |
| Print-job failure rate above threshold at one desk | High | Added |
| Retrospective violations surfaced by reconciliation | Medium — for review, not response | Added (`71`) |
| **Half or more of a site's devices silent within 2 minutes** | **Critical** — routed to both audiences | Added: the venue network or the platform is down |

Every event-day alert shows its **data age**. An alert computed from a device that last synced 20
minutes ago says so, and figures inside an unsynced window are provisional (`52`).

## Model — event-day alerts

```
alert_rules
  id, account_id NULL, event_id NULL,     -- NULL = platform default, overridable per event
  condition_key,                          -- QUEUE_THRESHOLD | ZONE_CAPACITY | DEVICE_OFFLINE | ...
  scope_type,                             -- EVENT | ZONE | ACCESS_POINT | DEVICE | PRINTER | SESSION
  threshold jsonb, severity,              -- HIGH | MEDIUM | CRITICAL
  runbook_key,                            -- anchor in 107; required
  enabled bool, timestamps

event_alerts
  id, event_id, condition_key,
  subject_type, subject_id,
  severity, status,                       -- OPEN | ACKNOWLEDGED | RESOLVED
  first_fired_at, last_fired_at, fire_count,
  cleared_at NULL,                        -- condition recovered; status does not change by itself
  data_age_seconds NULL,
  acknowledged_by NULL, acknowledged_at NULL,
  resolved_by NULL, resolved_at NULL, resolution_note NULL,
  incident_id NULL → incidents,
  timestamps
  UNIQUE (event_id, condition_key, subject_type, subject_id) WHERE status <> 'RESOLVED'
```

- **Group, do not multiply** (`60`): the partial unique index makes repeat firings update the open
  alert's `last_fired_at` and `fire_count`.
- **Recovery is not resolution.** `cleared_at` records that the metric recovered; a person resolves
  and says why — the same rule as incidents.

## Routing

| Alert | Goes to |
|---|---|
| Platform, Critical or High | Engineering on-call by pager; engineering channel |
| Platform, during an event window | Also the event's duty engineer |
| Event-day, High | Command center screen; push to the duty manager and the supervisor rostered for that location (`44`, `57`); SMS if push is not acknowledged (`43`) |
| Event-day, Medium | Command center; area supervisor |
| Cross-over (mass device silence) | Both, as one alert with two audiences |

The paging and chat tools are chosen with `84`; buy, do not build. Sentry's cron and uptime
monitoring may cover the synthetic and heartbeat checks — plan features `UNVERIFIED`.

## On call

| Role | When | May | May not |
|---|---|---|---|
| **Event duty engineer** | From T-24h to the end of breakdown (`56` anchors) | Restart and scale workers; switch feature flags (`123`); trigger device resync; declare a platform incident; request an emergency deploy | Change access rules; override access; resolve incidents about people |
| **Duty manager** (operations) | Doors-open windows | Acknowledge event-day alerts; confirm incidents (`60`); move staff | Touch infrastructure |
| **Engineering lead** | Escalation | Approve emergency deploys with the event director (`85`) | |
| **Platform on-call** | Business hours, best effort outside, until an SLA exists (`101`) | Platform response | |

**This is a staffing commitment, not software.** One engineer cannot be on call for every event day
and also build the platform. How the rota is staffed — hiring, contracting, or accepting reduced
cover — is `106`'s decision, and it bounds how many events ARZO can run at once.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | Browser Sentry SDK (M2) | Now — independent of hosting |
| 2 | Platform alerts: synthetic checks, queue age, scheduler heartbeat, failed jobs (M3), database | With `84`'s first environment |
| 3 | Dependency-aware readiness endpoint (M4, `76`) | With step 2 |
| 4 | On-call roles adopted in `106`; paging tool chosen | Before the first ARZO event on the platform |
| 5 | `alert_rules`, `event_alerts`; device and printer conditions | With ARZ-123 |
| 6 | Queue, zone, denial and staffing conditions; SMS escalation | With ARZ-170, `57` |
| 7 | Sampled tracing (M5) | Before Phase 4 field tests (`78`) |

## Open questions

- **Who is on call, and how many events at once can that support?** `106`.
- **Thresholds** — every number above is a guess to replace with pilot data.
- **Do clients see event-day alerts?** Government clients may want the T-24h review and the alert log (`59`).
- **SMS escalation cost** — small per event, but it needs a budget line (`43`, `99`).

## Related

`78-observability.md` · `53-live-event-command-center.md` · `60-incident-management.md` · `76-reliability.md` ·
`84-infrastructure.md` · `85-deployment.md` · `40-device-management.md` · `57-manpower-and-staffing.md` ·
`106-event-operating-model.md` · `107-event-day-runbook.md` · `126-disaster-recovery-plan.md` ·
`43-sms-notifications.md` · `44-push-notifications.md`
