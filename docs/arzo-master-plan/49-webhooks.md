# Webhooks

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep + Fix · **Priority:** P1 (ARZ-091) · **Phase:** fixes now, versioning with `48`
**Depends on:** `48-api-platform.md`
**Blocks:** `47-crm-integrations.md`, `50-third-party-integrations.md`

---

## Current state — `CONFIRMED`, ~70%: well-defended, but retries never happen

Earlier revisions scored this 85% on the strength of the SSRF defence. That defence is real. The
audit also found that **failed deliveries are never retried**, which for an integration mechanism is
most of the point.

### Schema

```
webhooks      id, url varchar(255), event_types jsonb, status default 'ENABLED',
              secret varchar(255) NOT NULL,           -- plaintext
              last_response_code, last_response_body, last_triggered_at,
              user_id, account_id, organizer_id NULL, event_id NULL,
              timestamps, deleted_at
webhook_logs  id, webhook_id, event_type, payload text,
              response_code, response_body, timestamps, deleted_at
```

Webhooks attach at **organizer** level (`api.php:342-347`) or **event** level (`:503-508`), with
create, list, update, get, delete and logs for each. Management UI: `WebhookForm`, the two
`*WebhookTable` components and their modals.

### Event types — 17, not 19

`DomainEventType.php:11-32`: `product.{created,updated,deleted}`,
`event.{created,updated,archived}`,
`order.{created,updated,marked_as_paid,refunded,cancelled}`,
`attendee.{created,updated,cancelled}`, `checkin.{created,deleted}`, `occurrence.cancelled`.
Earlier documents said 19; the enum has 17.

`attendee.created` and `attendee.cancelled` are also fanned out from the order events
(`WebhookDispatchService.php:162-180`).

### The SSRF defence — genuinely strong

`SecureCallWebhookJob` disables automatic redirects and follows up to three hops itself,
revalidating each (`:30-62`), and pins the resolved addresses with `CURLOPT_RESOLVE` (`:44`).
`WebhookUrlValidator` allows only http/https, blocks localhost-style and internal TLDs and cloud
metadata hosts, resolves A and AAAA, rejects private, reserved and link-local ranges, and unwraps
IPv4 embedded in mapped, NAT64, 6to4 and Teredo IPv6 addresses (`:17-194`).

The validator is tested (`WebhookUrlValidatorTest.php`, `NoInternalUrlRuleTest.php`). **The job is
not** — no test references `SecureCallWebhookJob` or `CURLOPT_RESOLVE`.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| W1 | **Retries never happen.** The HTTP call is `dispatchSync()`, so the job's `attempts()` is always 1, `release()` on a sync job is a no-op, and `tries = 3` plus `ExponentialBackoffStrategy` are dead configuration. `FinalWebhookCallFailedEvent` never fires. | `WebhookDispatchService.php:210`; `config/webhook-server.php:60-65` | **High** — a receiver down for one minute loses those events permanently |
| W2 | Head-of-line blocking: the synchronous call runs inside the dispatch job, so one slow endpoint (3 s timeout × hops) delays every later webhook on that worker | Same | Medium |
| W3 | **Payloads are the REST resources** (F14); the envelope `{event_type, event_sent_at, payload}` has no version and no delivery id | `WebhookDispatchService.php:61-184,199-203` | High, for the API platform |
| W4 | **F10 still open:** dashboard check-ins emit no `checkin.created` | `CheckInAttendeeHandler.php:104-131` | Medium — integrators see only scanner check-ins |
| W5 | Bulk status changes emit nothing: archiving an organizer archives its events with **no** webhooks | `UpdateOrganizerStatusHandler.php:73-81` | Medium |
| W6 | Secrets stored in **plaintext**, returned only on create, **no rotation endpoint** | `CreateWebhookService.php:28`; `Models/Webhook.php` casts | Medium |
| W7 | Duplicating an event copies its webhooks **with the same secret** | `DuplicateEventService.php:529` | Low |
| W8 | Log retention runs **inline on every delivery**, keeping the last 20 per webhook (`@todo This should be a scheduled task`) | `WebhookLogRepository.php:24-39` | Low — and 20 is too few to debug an outage |
| W9 | Logs store the enum **name** (`ORDER_CREATED`); payloads carry the **value** (`order.created`) | `WebhookDispatchService.php:208` | Low — confusing in support |
| W10 | `event.*` webhooks go to the `default` queue, not `webhook-queue` | `CreateEventHandler.php:96` etc. via `DispatchEventWebhookJob` | Low |
| W11 | No "send test event" endpoint | Search | Low — integrators test by creating real orders |
| W12 | `event_types` is not required to be an array | `UpsertWebhookRequest.php:16-17` | Low |

Two operational footguns to document rather than fix: a host in `APP_ALLOWED_INTERNAL_WEBHOOK_HOSTS`
bypasses **all** validation (`WebhookUrlValidator.php:57-63`), and a configured HTTP proxy bypasses
the DNS pinning (`SecureCallWebhookJob.php:51`).

`F13`, the webhook queue defaulting to a queue named `sync`, is **fixed**: `config/queue.php:16`
now defaults to `webhook-queue` (`c5ee755a`).

## Target

### Delivery — W1, W2

Dispatch `SecureCallWebhookJob` onto `webhook-queue` properly so the configured tries and backoff
work. Record each attempt; after the final failure, mark the delivery failed and count it toward
auto-disable.

```
webhook_deliveries   -- one per (webhook, event); webhook_logs become its attempts
  id uuid,                       -- the delivery id sent to the receiver
  webhook_id, event_type, payload_version,
  status,                        -- PENDING | DELIVERED | FAILED
  attempts int, next_attempt_at NULL, last_response_code NULL,
  created_at, delivered_at NULL
```

**Auto-disable** after N consecutive failed deliveries, with an email to the webhook's owner.
Silently retrying a dead endpoint forever is noise; silently disabling it is worse.

### Payload versioning — W3, F14

```
{
  "id": "<delivery uuid>",          -- receivers de-duplicate on this
  "event_type": "order.created",
  "version": "2026-10-01",          -- payload schema version
  "occurred_at": "...",
  "sent_at": "...",
  "data": { ... }                   -- from a versioned transformer, not a JsonResource
}
```

- Each webhook pins the `version` current at creation; new versions are opt-in per webhook.
- Today's payloads become version `legacy`, produced by the existing resources, so nothing breaks.
- The delivery `id` inside the signed body gives receivers both de-duplication and a replay check.

### Secrets — W6, W7

Encrypted cast at rest; a rotate endpoint with a grace period during which deliveries carry
signatures from both the old and new secret; duplication generates a fresh secret.

### New event types, by phase

| Phase | Types | Note |
|---|---|---|
| 1–2 | `checkin.created` from **both** paths (fixes W4 with ARZ-041) | |
| 2 | `accreditation.{submitted,approved,rejected}`, `credential.{issued,revoked}`, `badge.printed`, `access.override` | Low volume, high interest |
| 3 | `session.registration.{created,cancelled}`, `lead.captured` | `lead.captured` needs exhibitor-scoped subscriptions (`33`) |
| — | **Not** `access.granted` per scan | Gate volume would flood receivers. Offer an aggregate, or leave it to the API. |

Each new case must also be added to the webhook table in `config/scramble.php` and to
`WebhookForm`, per `CLAUDE.md`.

### Operations — W8, W9, W11

A scheduled retention job with a time-based window (30 days proposed) replacing the inline trim;
log the event type value, not the enum name; a "send test event" action per webhook with a
representative payload.

## Migration

| Step | Change | Risk |
|---|---|---|
| 1 | Queued delivery with working retries; test `SecureCallWebhookJob` (W1, W2) | Low — behaviour becomes what the config already claims |
| 2 | Scheduled log retention; value-not-name logging; test endpoint (W8, W9, W11) | Low |
| 3 | Encrypted secrets, rotation, fresh secret on duplicate (W6, W7) | Low — needs a backfill encrypting existing secrets |
| 4 | Envelope with `id` and `version`; `legacy` version for existing webhooks (W3) | Medium — the envelope changes shape; ship under a new version only |
| 5 | Versioned transformers | Medium |
| 6 | New event types as their domains land | Low |

Step 1 is the most valuable change in this document and the smallest.

## Open questions

- **Retention window** for delivery logs — 30 days, or per plan (`99`)?
- **Auto-disable threshold** — consecutive failures, or failure rate over a window?
- **Exhibitor-owned webhooks** — a new owner type, or leads delivered only through the organizer's webhooks?
- **Ordering guarantees** — none are made today. Receivers must tolerate `order.updated` arriving before `order.created` after a retry; worth stating in the developer docs.

## Related

`48-api-platform.md` · `47-crm-integrations.md` · `50-third-party-integrations.md` ·
`18-check-in.md` · `64-security.md` · `70-background-jobs.md` · `02-current-state-audit.md` (F10, F13, F14)
