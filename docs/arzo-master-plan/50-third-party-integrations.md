# Integration Framework

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Conventions now, framework later · **Priority:** P2 · **Phase:** 3
**Depends on:** `48-api-platform.md`, `49-webhooks.md`, `65-privacy-gdpr.md`
**Blocks:** `42-email-marketing.md` (ESP), `43-sms-notifications.md` (provider), `47-crm-integrations.md`

---

## Current state — no framework; nine direct integrations

`CONFIRMED` — every external service the backend talks to:

| Service | Purpose | Credentials | Pattern |
|---|---|---|---|
| **Stripe** (Connect, `express` default) | Payments, refunds, payouts | Platform keys global in env, one set per platform (default/CA/IE); connected `stripe_account_id` per organizer | Direct client (`StripeClientFactory.php:18-29`) |
| Stripe inbound webhook | Payment state | Global signing secrets | `POST /public/webhooks/stripe` (`api.php:652`) |
| Google Places | Venue address lookup | Global `GOOGLE_MAPS_API_KEY` | **Interface + NoOp fallback** (`GeoProviderInterface`, `AppServiceProvider.php:150-170`) |
| Anthropic via `laravel/ai` 0.11 | Event spam screening, `claude-haiku-4-5` | Global `ANTHROPIC_API_KEY` | Agent class (`EventSpamDetectionAgent.php:16-24`); gated by SaaS mode and a setting |
| VIES | EU VAT number validation | None | Direct HTTP (`ViesValidationService.php:15`) |
| OpenExchangeRates | Currency conversion | Global | **Interface + NoOp fallback** (`CurrencyConversionClientInterface`) |
| Mail (SMTP, SES, Mailgun, Postmark) | All email | Global | Laravel mail transports |
| S3 | Public and private files | Global | Laravel filesystems |
| Sentry | Errors | Global DSN | SDK |

Not present: any per-account credential store (no encrypted casts anywhere — `CONFIRMED`), OAuth
client code, or a generic connection table. `EncryptedPayloadService` produces expiring tokens for
invitations; it stores nothing.

Two of these already follow a good pattern — **an interface bound in the container with a NoOp
fallback** (Geo and currency). That is the house style to extend, not replace.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| I1 | **The Stripe webhook accepts before verifying.** The action queues a closure and returns `204` **before** checking the signature; verification happens later in the job, trying each platform secret. Unsigned payloads are always accepted and queued, and the full payload is logged if handling fails. | `StripeIncomingWebhookAction.php:21-39`; `IncomingWebhookHandler.php:141-171` | **Medium** — forged events are rejected in the job, but the endpoint is an unauthenticated queue-filler behind only the global 180/min limit, and logs are a sink for attacker-controlled content |
| I2 | **Razorpay class extends a non-existent abstract** | `RazorpayOrderDomainObject.php` — no `Generated\RazorpayOrderDomainObjectAbstract`, no table, no references | Low — dead code that fatals if ever autoloaded; delete it per `CLAUDE.md` |

**I1 fix:** verify the signature synchronously in the action — it is a cheap HMAC — and return
`400` on failure. Queue only verified events. Log event ids, not bodies.

## Decision: conventions now, framework at the second per-account integration

The scaffold's rule stands — one integration does not justify an abstraction. But every
integration so far uses **platform-level** credentials. The first **per-account** integration
(an organizer's CRM or ESP) is what needs new infrastructure. Build it then, to these conventions,
decided now so the first one does not set a worse precedent.

### Convention 1 — interface plus NoOp

Every provider sits behind an interface with a NoOp or log implementation, as Geo and currency do.
Development and tests run with no external accounts; a missing key degrades a feature rather than
crashing a request.

### Convention 2 — where credentials live

| Scope | Store |
|---|---|
| Platform (ARZO's own Stripe, SMS provider, mail) | Environment and config, as today |
| **Per account** (an organizer's CRM, ESP) | `integration_connections`, **encrypted cast**, never returned by the API after creation |

```
integration_connections
  id, short_id, account_id, organizer_id NULL,
  provider,          -- HUBSPOT | SALESFORCE | MAILCHIMP | ...
  status,            -- CONNECTED | ERROR | REVOKED
  credentials text,  -- encrypted: OAuth tokens or API key
  scopes jsonb NULL, settings jsonb,       -- field map, sync options
  last_success_at NULL, last_error NULL, error_count int default 0,
  connected_by → users, timestamps, deleted_at
```

### Convention 3 — outbound calls

Always from a queued job, never inline in a request. Explicit timeout, bounded retries with backoff
(and, unlike webhooks today, retries that actually run — `49` W1), an idempotency key wherever the
provider supports one (the Stripe refund call already does), and a per-connection error count that
flips `status` to `ERROR` and tells the owner.

### Convention 4 — inbound webhooks

Verify the signature **before** accepting (I1). Reject oversized bodies. Store the provider's event
id and ignore duplicates. Log ids, not payloads.

### Convention 5 — every provider is a data processor

Each integration that receives personal data needs a processor agreement and a known data location
(`65`). Worth recording now, because it is already true of mail, S3 and Sentry. The spam checker sends
**event content** to Anthropic, not attendee data — keep it that way.

## Integration candidates, in order

| # | Integration | Scope | Driver |
|---|---|---|---|
| 1 | SMS / WhatsApp provider | Platform | `43` — operational messaging |
| 2 | ESP audience sync | Per account or platform | `42` — `04` says integrate, not build |
| 3 | CRM | Per account | `47` — the one ARZO uses |
| 4 | Additional payment gateway | Platform | ARZ-191 — a Qatar-local gateway for local debit; which one is `UNVERIFIED` |
| 5 | Accounting (invoice sync) | Per account | Only if finance asks; invoices already export |
| — | **Zapier / Make** | — | No build: API keys (`48`) plus working webhooks (`49`) give breadth for free |

Item 1 and 4 are platform-level and need **no** framework. The framework in Convention 2 is first
needed by item 2 or 3.

## Open questions

- **ESP and CRM — platform-level for ARZO's own events, or per-account for SaaS customers?** Platform-level first is simpler and matches the buyer question in `04`.
- **Data residency** — do any providers need to keep data in-region for government clients? Decide per provider before signing.
- **Should the spam checker run for ARZO's own events at all?** It is gated off outside SaaS mode already; confirm that is the intended configuration.

## Related

`42-email-marketing.md` · `43-sms-notifications.md` · `47-crm-integrations.md` ·
`48-api-platform.md` · `49-webhooks.md` · `15-payments-invoicing-vat.md` ·
`65-privacy-gdpr.md` · `133-ai-capabilities.md`
