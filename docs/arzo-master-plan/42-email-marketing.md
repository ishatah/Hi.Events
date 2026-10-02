# Email Messaging and Marketing

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix + Extend; integrate an ESP for campaigns · **Priority:** P2 (ARZ-142) · **Phase:** fixes now, segmentation any time
**Depends on:** `41-event-marketing.md`, `65-privacy-gdpr.md`, `69-notifications-architecture.md`
**Blocks:** `43-sms-notifications.md` (shares audiences)

---

## Current state — `PARTIAL`: good operational messaging, no marketing foundations

### What exists — `CONFIRMED`

| Piece | Detail |
|---|---|
| `messages` | Subject, body, `type`, recipient/attendee/product id lists, `status`, `scheduled_at`, `event_occurrence_id`, `eligibility_failures` |
| `outgoing_messages` | One row per recipient email — **email string only**, no attendee or order id |
| Audiences — `MessageTypeEnum` | `ORDER_OWNER`, `TICKET_HOLDERS`, `INDIVIDUAL_ATTENDEES` (max 50), `ALL_ATTENDEES`, `ORDER_OWNERS_WITH_PRODUCT`; resolved in `SendEventEmailMessagesService.php:67-83` |
| Scheduled sends | `SendScheduledMessagesJob`, every minute |
| Anti-abuse tiers | `account_messaging_tiers`: Untrusted 3/day × 100 recipients, no links · Trusted 10 × 5,000 · Premium 50 × 50,000 |
| Review queue | Untrusted accounts go to `PENDING_REVIEW`; admin approves (`ApproveMessageHandler.php`); **there is no reject route** |
| Templates | `email_templates` with Liquid (`liquid/liquid ^1.4`) — but only for 3 system types: order confirmation, attendee ticket, occurrence cancellation |
| Marketing opt-in | `orders.opted_into_marketing_at`, shown when `event_settings.show_marketing_opt_in` |

Organizer messages do **not** use the Liquid templates. They render one Blade view with no
per-recipient merge fields.

### What is absent — `MISSING`

- **Unsubscribe of any kind.** No link, no suppression list, no `List-Unsubscribe` header, no
  per-attendee preference. The footer offers `mailto:` links only (`emails/event/message.blade.php:18-24`).
- **Use of the opt-in.** `opted_into_marketing_at` is read only by the orders export. No audience
  filters on it.
- Bounce and complaint handling, open and click tracking, segmentation, automation.

## Defects — fix before anything is added

| # | Defect | Evidence | Severity |
|---|---|---|---|
| E1 | **"Send test" emails real customers** for `ORDER_OWNER` and `ORDER_OWNERS_WITH_PRODUCT`. `is_test` short-circuits only in the attendee path, and the UI offers test mode for every type. | `SendEventEmailMessagesService.php:119-131,141,226-249`; `SendMessageModal/index.tsx:558` | **High** — an organizer testing a draft sends it to every buyer |
| E2 | **Every new ARZO account gets the Premium tier** (50 messages/day × 50,000 recipients). The default is `is_hi_events ? 1 : 3` and `APP_IS_HI_EVENTS` is absent from `.env.example`. | `CreateAccountHandler.php:227-229` | **High** if ARZO ever offers self-serve signup — anti-abuse is disabled by default |
| E3 | **Scheduled messages bypass tier limits.** Eligibility is checked at creation, not at dispatch, and the 24 h count uses `created_at` of certain statuses, so scheduled sends slip past it. | `MessageRepository.php:60-71`; `MessageDispatchService` | Medium |
| E4 | `outgoing_messages.status = SENT` means *queued*, not delivered. The mailable re-queues itself, so SMTP failures are invisible to the job's `try/catch`. | `SendEventEmailJob.php:36-50` | Medium — delivery reporting is fiction |
| E5 | No reject route for messages under review | Admin routes | Low — reviewers can only approve or ignore |

E1 and E2 are release-blocking for any external use.

## The distinction everything depends on

| | Operational | Marketing |
|---|---|---|
| Example | "Doors open at 18:00", "Room changed", "Your ticket" | "Tickets for our next event are on sale" |
| Audience | People with a ticket to **this** event | People from **past** events, or prospects |
| Lawful basis | Contract / legitimate interest | **Consent** |
| Unsubscribe | Not required for strictly operational content | **Required** |

Today's messaging tool is event-scoped and therefore **mostly operational** — which is why its lack
of unsubscribe is defensible for now. The moment an organizer can message *across* events, it is
marketing, and every row in the right-hand column becomes mandatory.

## Prerequisites before any cross-event or marketing send

1. **Suppression list and one-click unsubscribe.** Beyond the law: Gmail and Yahoo's 2024 bulk-sender
   rules require one-click unsubscribe (RFC 8058 `List-Unsubscribe-Post`), authenticated domains, and
   a low complaint rate. Missing them damages deliverability for **every** message ARZO sends,
   transactional included, because it shares the sending domain.

   ```
   email_suppressions  id, account_id, email_hash, scope,   -- ACCOUNT | ORGANIZER | GLOBAL
                       reason,  -- UNSUBSCRIBE | BOUNCE | COMPLAINT | MANUAL
                       created_at
   ```

2. **Bounce and complaint ingestion** from the provider's webhook (SES via SNS, or Postmark — whose
   mailer is already installed) into the suppression list.
3. **Consent that is actually consulted** — the opt-in, or a purpose-bound consent record (`65`).

## Segmentation — build; ARZO is the source of truth for audiences

A filter builder over **allowlisted** fields, reusing the operators `applyFilterFields()` already
supports (`eq/ne/lt/lte/gt/gte/like/in`):

| Dimension | Available |
|---|---|
| Event, product, order status, occurrence | Now |
| Question answers | Now |
| Locale, marketing opt-in | Now |
| Checked in / no-show | After `access_logs` is authoritative (`18`, `54`) |
| Session registered or attended | Phase 3 (`27`) |
| Exhibitor lead of X | Phase 3 (`33`) — and only with lead-sharing consent |

```
audience_segments  id, short_id, account_id, event_id NULL, name,
                   definition jsonb,       -- compiled server-side against the allowlist
                   last_count int NULL, last_counted_at NULL,
                   created_by, timestamps, deleted_at
```

Segments feed both the in-platform message tool (operational) and ESP sync (marketing).
**Suppressions always apply**, whichever path sends.

## Campaigns and automation — integrate, do not build

`04` decided this: drip sequences, A/B testing and campaign analytics are a product in themselves.
ARZO **pushes segments** to the ESP ARZO chooses and remains the system of record for audience and
consent. Push-only; the ESP's unsubscribes flow back as suppressions.

The one automation worth building in-platform is **operational**: event reminders ("tomorrow at
18:00", "know before you go"). That is scheduled messages plus templates — both exist.

## Merge fields

Extend organizer messages to Liquid with a **fixed, safe variable set** (first name, event name,
ticket link, session times). The template engine is already a dependency; the risk to manage is
exposing variables that leak other recipients' data, which an allowlist prevents.

## Tracking

**Clicks only**, opt-in, disclosed. Open tracking is unreliable since Apple Mail Privacy Protection
pre-fetches images, and it is the more intrusive of the two. Click tracking rewrites links, so it
must never apply to ticket and magic links, whose tokens would transit the tracking domain.

## Open questions

- **Which ESP?** None is integrated. The one ARZO's marketing team already uses wins.
- **Does ARZO market across its own events?** If yes, the prerequisites above are Phase-independent P1 work.
- **Default messaging tier for ARZO-operated accounts** — fixing E2 should set Untrusted for self-serve and a deliberate tier for ARZO's own accounts.
- **Arabic templates** — the three system templates need Arabic variants if `82` confirms Arabic scope.

## Related

`41-event-marketing.md` · `43-sms-notifications.md` · `65-privacy-gdpr.md` ·
`69-notifications-architecture.md` · `54-attendance-intelligence.md` · `04-product-strategy.md`
