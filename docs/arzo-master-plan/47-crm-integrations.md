# CRM Integrations

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Integrate · **Priority:** P2 (ARZ-180) · **Phase:** 3, after the API platform
**Depends on:** `48-api-platform.md`, `49-webhooks.md`, `50-third-party-integrations.md`
**Blocks:** `56-event-operations.md` (the hand-off from sales to delivery)

---

## Current state — `MISSING`, 0%

`CONFIRMED`: no HubSpot, Salesforce, Pipedrive, Zoho or other CRM code. Zapier and Make appear only
as help links beside the webhook table (`WebhookTable/index.tsx:212-214`).

What an integration would be built on, and its condition today:

| Foundation | State | Blocks a CRM integration because |
|---|---|---|
| Outbound webhooks, 17 event types | `CONFIRMED` | — usable today via Zapier/Make |
| Webhook payloads | Reuse REST `JsonResource` classes (F14) | Any API refactor silently changes what the CRM receives |
| Webhook retries | **Ineffective** — `dispatchSync()` defeats `tries=3` and backoff (`49`) | A CRM that is briefly down loses the event |
| Machine authentication (API keys) | `MISSING` (`48`) | A CRM cannot call back into ARZO without impersonating a user |
| Per-account credential storage | `MISSING` — no encrypted credential store (`50`) | A native connector has nowhere to keep its OAuth tokens |

## Two different problems called "CRM integration"

They get conflated, and they have different owners, data and directions.

| | A — ARZO's own sales pipeline | B — Participant data into a CRM |
|---|---|---|
| Records | Clients, prospects, proposals, contracts | Buyers, attendees, exhibitor leads |
| Whose CRM | ARZO's | ARZO's, an organizer's, or an exhibitor's |
| Direction | CRM → ARZO at contract signature | ARZO → CRM, continuously |
| Relates to | `56` event lifecycle | `33` leads, `42` audiences |

### A — sales pipeline stays in the CRM

`56` asks whether the pipeline belongs in ARZO. **It should not.** A CRM is someone else's core
competence (`04`), and ARZO operations begin when a deal becomes an event.

The integration is a single hand-off: **deal won → draft event in ARZO**, created from an event
template (`56`), carrying client, dates, venue and expected size. Implemented as a CRM workflow
calling ARZO's API — which needs API keys (`48`). Until then, the hand-off is manual, and that is
fine at ARZO's current volume.

### B — participant data, three tiers

| Tier | Mechanism | When |
|---|---|---|
| 1 | **Webhooks + Zapier/Make** | Now — works today, once `49`'s retry defect is fixed |
| 2 | Scheduled or on-demand exports | Now — attendees, orders, affiliates exist; leads with `33` |
| 3 | A **native connector** for one CRM, push-only | Only for the CRM ARZO itself uses, and only once tiers 1–2 prove insufficient |

The scaffold's instinct stands: **build the one CRM ARZO uses properly, not a generic framework
nobody exercises.** Which CRM that is remains `UNVERIFIED`.

## If a native connector is built

Push-only. Bidirectional sync creates conflict resolution — which system wins when a name is edited
in both — and ARZO has no reason to accept CRM edits into attendee records.

| Concern | Design |
|---|---|
| Credentials | OAuth tokens in the per-account encrypted store (`50`), never in `.env` |
| Idempotency | An external-id map, so a retried push updates rather than duplicates |
| Mapping | Fixed default field map, organizer-editable for custom CRM fields |
| Triggers | Domain events already feeding webhooks — order completed, attendee created, lead captured |
| Failure | Retries with backoff via the queue; per-record sync log visible to the organizer |
| Rate limits | Honour the CRM's; batch where its API allows |

```
integration_external_ids  id, integration_connection_id,
                          entity_type, entity_id, external_id,
                          last_synced_at, last_hash
                          UNIQUE (integration_connection_id, entity_type, entity_id)
```

`last_hash` skips unchanged records — most syncs send nothing.

## Data protection

Pushing attendee data into a CRM is a transfer to wherever that CRM stores it (`65`):

- **Never sync** ID document numbers, date of birth, nationality or photos (`23`) — the connector's
  field allowlist excludes them outright, not by default.
- Marketing use in the CRM needs the marketing consent that `42` shows is captured but unused.
- An attendee deletion in ARZO cannot recall data already pushed; the connector should at least
  send a deletion signal where the CRM API supports it.

## Exhibitor leads

Exhibitors want leads in **their** CRMs. v1 is export (`33`). A webhook to an exhibitor-owned
endpoint needs exhibitor-scoped webhook subscriptions, which do not exist — webhooks today belong
to organizers and events (`49`).

## Open questions

- **Which CRM does ARZO use?** Everything in tier 3 waits on this.
- **Does the sales team want event status reflected back into the CRM** (published, sold, completed)? That is the one legitimate ARZO → CRM flow for problem A, and it is a webhook.
- **Organizer-owned connectors** — do SaaS customers need their own CRM connections, or is Zapier enough? Enough, until a customer says otherwise.

## Related

`48-api-platform.md` · `49-webhooks.md` · `50-third-party-integrations.md` ·
`33-exhibitor-lead-capture.md` · `42-email-marketing.md` · `56-event-operations.md` ·
`65-privacy-gdpr.md` · `04-product-strategy.md`
