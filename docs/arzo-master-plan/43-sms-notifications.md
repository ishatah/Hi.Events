# SMS and Messaging Channels

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New (buy a provider) · **Priority:** P2 (ARZ-140) · **Phase:** 3
**Depends on:** `69-notifications-architecture.md`, `42-email-marketing.md`, `65-privacy-gdpr.md`
**Blocks:** `44-push-notifications.md` (fallback channel), `30-mobile-event-app.md`

---

## Current state — `MISSING`, 0%, and phone data is not collected reliably

`CONFIRMED`: no SMS or WhatsApp provider code — searches for Twilio, Vonage, Nexmo, MessageBird,
Unifonic, Infobip and "sms" return nothing. "WhatsApp" appears only as an organizer social link and
a share button.

The harder finding is upstream of any provider: **ARZO does not hold usable phone numbers.**

| Fact | Evidence |
|---|---|
| `attendees` and `orders` have no phone column | Live schema |
| `persons.phone`, `invitations.phone` exist — `varchar(64)`, schema only, nothing writes them | `2026_09_29_000005`, `2026_09_30_000003` |
| `organizers.phone` is a free string, max 25 | `UpsertOrganizerRequest.php:17` |
| `QuestionTypeEnum::PHONE` exists in the backend, **but the frontend `QuestionType` enum lacks it** | `QuestionTypeEnum.php:10`; `frontend/src/types.ts:1167-1175` |
| No normalization — no `libphonenumber`, no E.164 | Search |

So the only way a phone number enters the platform today is a demo seeder's `PHONE` question that the
organizer UI cannot create. **An SMS provider integration is useless until phone capture exists.**
That is step one, not the provider.

## What SMS is for

`04` put SMS under "buy", and the scaffold was right that it belongs on high-value paths. Per message
it costs orders of magnitude more than email, and it interrupts.

| Use | Verdict |
|---|---|
| Ticket link for attendees without reliable email | **Yes** |
| Event-day operational changes — gate moved, session relocated, delay | **Yes** — the highest-value case |
| Cancellation and postponement | **Yes** |
| Walk-in confirmation at the door (`17`) | Yes |
| One-time codes, if attendee accounts arrive (`30`) | Yes |
| Staff shift reminders and callouts (`57`) | Yes |
| Marketing and promotions | **No** — cost, consent burden, and the fastest way to get a sender ID blocked |

## WhatsApp — assess alongside, not after

In Qatar and the GCC, WhatsApp is often the channel people actually read. The WhatsApp Business
Platform supports approved message templates for transactional notifications.

| | SMS | WhatsApp |
|---|---|---|
| Reach | Universal | Very high in the GCC; `UNVERIFIED` against ARZO's audiences |
| Arabic | Costs more per message (below) | No segment penalty |
| Setup | Sender ID registration | Business verification + per-template approval |
| Delivery evidence | Delivery receipts | Delivered and read receipts |

**Recommendation:** build the channel abstraction once (`69`) and plan for both. Launch with the
one whose onboarding completes first; the lead time of either is the real constraint.

## Arabic doubles or triples the cost

An SMS segment holds **160 characters in GSM-7** but only **70 in UCS-2**, which any Arabic
character forces. Concatenated messages drop to 153 and 67 per segment. A 140-character Arabic
message is therefore **three** billed segments where the English equivalent is one.

Consequences: Arabic templates must be written short; the send UI must show the segment count before
sending; and cost estimates in `99` must assume Arabic pricing if `82` confirms Arabic scope.

## Provider selection

Criteria, in order — reliable Qatar and GCC delivery comes first, because a provider that drops
Ooredoo or Vodafone Qatar routes is worse than none:

1. Local delivery quality and direct operator routes in Qatar
2. Sender ID registration support and lead time in Qatar (`UNVERIFIED` — regulatory specifics need confirming)
3. Delivery receipts via webhook
4. WhatsApp Business Platform on the same account
5. Data residency and a processor agreement (`65`)

Candidates to evaluate: global (Twilio, Vonage, Infobip) and regional (Unifonic, or an aggregator
contracted with Qatar operators). **No choice is made here** — it needs quotes and a delivery test to
real Qatari numbers.

## Design

Behind the notification abstraction (`69`) so audiences, templates and preferences are shared with
email and push:

```
SmsChannel → SmsProvider (interface)
               send(to_e164, body, sender_id) -> provider_message_id
               parseDeliveryReceipt(request) -> { id, status, error }
```

| Concern | Rule |
|---|---|
| Phone capture | E.164 on input with a country default (`QA`), validated with a libphonenumber port |
| Consent | Operational SMS to ticket holders under the ticket contract; anything else opt-in |
| Opt-out | Honour `STOP` replies via the provider's inbound webhook → suppression (`42`) |
| Quiet hours | No non-urgent SMS overnight, venue-local time |
| Cost control | Per-account monthly budget and per-message recipient cap, reusing the messaging-tier idea |
| Delivery status | Receipts update a per-recipient delivery row; "sent" never means "delivered" (the email defect E4 in `42` must not be repeated) |
| Retries | Provider-level only — never resend an SMS the provider accepted, or recipients get duplicates |

## Migration

| Step | Change |
|---|---|
| 1 | Add `PHONE` to the frontend question types; E.164 normalization on save |
| 2 | Optional phone capture at checkout, per event setting, writing to the attendee's person |
| 3 | Channel abstraction (`69`); first provider adapter; delivery receipts |
| 4 | Operational templates in English and Arabic; segment count shown before send |

Step 1 is small and worth doing ahead of the rest — it fixes a live backend/frontend enum mismatch.

## Open questions

- **SMS, WhatsApp, or both first?** Decided by onboarding lead time more than by preference.
- **Sender ID** — alphanumeric "ARZO", or a number? Alphanumeric cannot receive `STOP` replies in many markets, which changes the opt-out design.
- **Who pays** — ARZO absorbing SMS cost for its own events, or passed through for SaaS customers (`99`)?
- **Is phone mandatory at checkout?** Mandatory raises abandonment; optional means SMS reaches only part of the audience.

## Related

`42-email-marketing.md` · `44-push-notifications.md` · `69-notifications-architecture.md` ·
`65-privacy-gdpr.md` · `82-localization.md` · `17-onsite-registration.md` ·
`57-manpower-and-staffing.md` · `99-pricing.md`
