# Fraud Prevention

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Fix + New (detection) · **Priority:** P1 for FR1–FR3 and FR5 (unnumbered — add to `136` when scheduled); FR4 in ARZ-314/ARZ-321 (P0); FR6 is ARZ-327, FR7 is ARZ-309; detectors P2, unnumbered · **Phase:** fixes now; clone detection with Phase 4 offline scanning
**Depends on:** `24-access-control.md`, `36-rfid-nfc.md`, `38-scanner-platform.md`, `40-device-management.md`, `67-audit-logging.md`, `71-realtime-architecture.md`
**Blocks:** `53-live-event-command-center.md` (fraud alerts), `60-incident-management.md` (confirmed signals become incidents)

---

Abuse of tickets, codes, credentials and the platform itself: gate-crashing with a copied or
forged pass, harvesting ticket codes, brute-forcing access codes, bulk buying, card testing, and
misuse of reprints and offline mode. The rule throughout: **detect and flag first; block only where
the signal cannot be a false positive, or a human has confirmed it.**

## Current state — `PARTIAL`, ~25%: platform-abuse controls exist; ticket and credential controls do not

| Control | State | Evidence |
|---|---|---|
| AI-assisted event spam screening with an admin approve / confirm queue | `CONFIRMED` — gated by SaaS mode | `EventSpamCheckJob`; admin routes `api.php:575-578` |
| Messaging tiers against bulk-mail abuse | `CONFIRMED`, but every new account defaults to Premium and scheduled sends bypass limits | `42` E2, E3; ARZ-305 |
| Account verification before publishing | `CONFIRMED` | `56` |
| Global rate limit: 180/min per user or IP, on every route | `CONFIRMED` | `RouteServiceProvider.php:27-30`; `config/app.php:27`; `Http/Kernel.php:71` |
| Per-route throttles: organizer contact 5/min; waitlist, promo lookup, ticket lookup 10/min; occurrences 60/min; self-service edits and emails 20/hour per order | `CONFIRMED` | `api.php:612-677`; `RouteServiceProvider.php:32-38` |
| Order creation, completion and payment intents: **global limit only**; no bot challenge anywhere | `CONFIRMED` | `api.php:624-646`; no CAPTCHA or Turnstile in the repository |
| One PaymentIntent per order, reused; session-verified; order must be `RESERVED` and unexpired | `CONFIRMED` | `CreatePaymentIntentHandler.php:66-70,98-104` |
| Per-order quantity cap, **default 100 per product**; no cap across orders | `CONFIRMED` | `OrderCreateRequestValidationService.php:471` |
| Ticket QR is the attendee `public_id` `A-XXXXXXX` (~36 bits), no unique index | `CONFIRMED` | `IdHelper.php:32-35`; `33` |
| Credential identifiers: 40 random lower-case characters, SHA-256 hash for lookup | `CONFIRMED` | `CredentialIssuanceService.php:86-93` |
| Ticket transfer feature | `MISSING` — but a rename is possible (FR6) | No transfer code; `EditAttendeePublicRequest.php:12-14` |
| Credential clone detection | `MISSING` | No detector; SUN verification designed only (`36`) |
| Stripe Radar rules | `UNVERIFIED` | Configured in Stripe, not in the repository |

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| FR1 | **The public check-in list returns every attendee's ticket code.** Anyone holding a check-in link gets first name, last name and `public_id` for the whole list — and the QR *is* the `public_id`, so each row is a working ticket | `AttendeeWithCheckInPublicResource.php:18-22`; `api.php:657` | **High. Fix now** — see surface 1 |
| FR2 | **Promo-code brute force bypasses the 10/min throttle.** `POST /public/events/{id}/order` accepts `promo_code`, applies it silently if valid, and returns it — at the global 180/min. Codes unlock hidden products, and a `NONE`-discount code is a pure access key (`46`) | `CreateOrderRequest.php:29`; `CreateOrderHandler.php:95-110`; `OrderResourcePublic.php:46` | **High** for events using codes for VIP or comp allocations. **Fix now** |
| FR3 | **Credential identifiers are stored in plaintext next to their hash, and every scan copies the identifier into `access_logs.raw_identifier`.** A database read, a backup or a log export yields working credentials; the hash protects nothing | `CredentialIssuanceService.php:92-93`; `AccessScanService.php:221` | **High** for high-security events. **Fix now** — both tables are empty |
| FR4 | Scans carry no device id; the service drops the parameter | `AccessScanService.php:38,211-228` (`67` A3) | Medium — clone and device-compromise investigations both need it. **Fix now** (ARZ-314, ARZ-321) |
| FR5 | Order creation and payment intents are unthrottled beyond 180/min per IP: card testing and inventory hoarding (each order reserves stock for up to 120 minutes; replacement only within one cookie session) | `api.php:624-646`; `UpdateEventSettingsRequest.php:30`; `CreateOrderHandler.php:65-66` | Medium |
| FR6 | A self-service rename changes the ticket holder but **not the ticket code** — an email change rotates the ticket link's `short_id`, never the `public_id` the QR encodes, so the previous holder still has a valid ticket | `SelfServiceEditAttendeeService.php:59-75`; gated by `SelfServiceValidationTrait.php:15` | Medium — ARZ-327 (`91` T2) |
| FR7 | Public check-in: any link holder can undo check-ins; search matches email (an enumeration oracle) | `api.php:661`; `AttendeeRepository.php:130` (`38`) | Medium — ARZ-309 |
| FR8 | Exports include the ticket-code column by default | `AttendeesExport.php:64` (`65` PV10) | Medium |

## Principle: flag before block

Every detector writes a **signal**; a person decides what it means. The patterns already exist and
work: spam checks (AI screens, an admin confirms) and incidents (`60`: alerts propose, humans
confirm). Blocking happens through mechanisms that already exist and are audited (`67`) — revoke a
credential, add a `DENY` rule, suspend a device, cancel an order — never through a hidden fraud
flag that silently refuses someone at a door.

There is one exception: **cryptographic failures block immediately** — an NTAG 424 DNA SUN counter
that did not increase (`36`), a forged signature. They have no innocent explanation.

## Surface 1 — public check-in links

Today the link is the only credential (F3) and it discloses the roster with ticket codes (FR1).

- **Stop returning `public_id` in list and search responses.** The scanner uses it in two places:
  as the check-in key and to match a scanned code against the fetched list
  (`CheckIn/index.tsx:234,347`). Check-in from a search result moves to the attendee's `id` or
  `short_id`; a scanned code resolves through the single-attendee endpoint. Two-sided, small.
- Remove email from the public search; undo requires a supervisor; device-bound links (`38`, `40`).
- A per-link throttle on lookups by code, so a leaked link cannot be used to test guesses at speed.

## Surface 2 — checkout: card testing and hoarding

Card data never touches ARZO (`66`), so card testing through ARZO is an **abuse of Stripe via ARZO's
checkout**: create an order, obtain a PaymentIntent, try cards against it in the browser. In SaaS
mode the charges sit on the organizer's connected account (`66`), and so do the fees and disputes.

| Control | Where |
|---|---|
| Per-IP and per-event throttles on order creation and payment-intent creation (for example 10/min per IP per event) | New named limiters, as `RouteServiceProvider` already does for self-service |
| Stripe Radar rules for card-testing patterns | Stripe configuration — `UNVERIFIED`, finance owns it |
| A signal when failed payment attempts on one event spike (`stripe_payments.last_error`, payment-failed webhooks) | Detector below |
| A bot challenge on order creation **only for events flagged high-demand**, or when the signal fires | Not everywhere — it costs conversion |

Hoarding — many reserved orders locking stock — gets the same throttle, plus a cap on concurrent
`RESERVED` orders per IP per event.

## Surface 3 — promo and access codes (FR2)

- Apply the **same limiter to every path that evaluates a code**: the lookup and order creation.
  Failed code evaluations count per IP per event; after N failures in an hour, codes are ignored for
  that IP and a signal is raised.
- **Access-key codes must be unguessable.** For codes with `NONE` discount or that unlock hidden
  products, generate random codes of at least 8 characters from an unambiguous alphabet (`46`
  already proposes one for batches) and warn when an organizer types a short dictionary word.
- Single-use batch codes (`46`) for comps and sponsor passes turn a leaked code into one lost ticket,
  not an open door.

## Surface 4 — bulk buying and scalping

Honest position: **purchase limits deter casual resale, not determined scalpers.** Emails are free,
cards are many, and `46` already notes that email-based limits are trivially bypassed.

| Control | Strength |
|---|---|
| Per-order cap (exists, default 100 — lower it for high-demand events) | Weak |
| Signals: many orders from one IP, one card fingerprint or one email domain on one event | Useful for review, especially with Stripe's payment-method fingerprint |
| **Named tickets and a name check at entry** for high-demand events, with transfer only through the platform | Strong — the only control that actually defeats resale |
| Waitlist-based release (`14`) instead of first-come on-sale | Strong against bots at on-sale |

Resale price caps and secondary-market rules are regulatory questions — `UNVERIFIED` for Qatar (`66`).

## Surface 5 — ticket transfer

There is no transfer feature. There is a rename (FR6), which is a transfer without the one property
that makes transfer safe: **the old code must stop working.**

Design:

```
ticket_transfers
  id, short_id, event_id, attendee_id → attendees,
  from_name, from_email, to_name, to_email,
  requested_via,          -- SELF_SERVICE | ORGANIZER
  status,                 -- PENDING | ACCEPTED | CANCELLED | EXPIRED
  old_public_id,          -- kept for dispute resolution
  requested_at, completed_at NULL, timestamps
```

- Accepting a transfer **rotates `public_id`** and, once credentials are live, the credential's QR
  medium (`36`); the old code is denied as `REVOKED`, and both parties are emailed.
- Organizer settings: transfers on or off, a cutoff (for example T-24h), a maximum per ticket.
- **Fraud versus legitimate transfer:** a legitimate transfer goes through this flow and rotates the
  code. Two people presenting the same code is fraud, whichever holds it — which is exactly the
  clone signal below.
- Self-service rename keeps working for spelling corrections, but a change of email counts as a
  transfer and rotates the code.

## Surface 6 — credential cloning, the new surface

A printed QR is trivially photocopied; an NTAG21x UID can be copied onto a "magic" card (`36`). The
defence is detection from the scan stream, which `access_logs` already records.

| Signal | Rule | Default action |
|---|---|---|
| **Impossible travel** | Same credential `GRANTED` at two access points faster than their minimum transit time | Signal HIGH; command-center alert (`53`) |
| **Double presence** | Credential inside two zones that cannot both hold it (no exit between entries) | Signal MEDIUM |
| **Replaced credential used** | A credential or medium marked `REVOKED`, `LOST` or `REPLACED` is presented (`21`: a voided-and-reissued credential at a gate is a signal) | Denied already; signal HIGH — the old badge is in someone's hand |
| **UID reuse** | A scanned UID matches a `LOST` or `REPLACED` medium, or resolves to a different credential than the printed QR on the same badge | Signal HIGH |
| **SUN counter replay** | Counter not greater than `last_sun_counter` | **Deny online.** Offline: each device checks its own last counter; the server flags cross-device regressions at sync |
| **Probing** | Many denials for one credential across access points in minutes | Signal MEDIUM |
| **Forgery burst** | `DENIED_NO_CREDENTIAL` rate spikes at one access point | Signal MEDIUM, per access point |

Minimum transit time needs a notion of distance, which the space model does not have: `25` keeps
`access_points.position` loose until the command center picks a coordinate system. So:

```
access_point_transit              -- configured per venue; defaults filled in
  access_point_a_id, access_point_b_id,
  min_transit_seconds int         -- 0 = same gate group; default 120 between different zones
  UNIQUE (access_point_a_id, access_point_b_id)
```

Access points at the same gate share a **gate group** with transit 0, so a badge re-scanned at the
next lane is not flagged.

## Surface 7 — badge reprint abuse

`21` keeps the credential on reprint. That opens the obvious abuse: claim "damaged", receive a second
badge, hand it to a friend — **both work**.

- **A reprint issues a new QR medium and marks the old one `REPLACED`** (`36`'s `credential_media`).
  The credential survives, as `21` intends; the old paper stops opening doors.
- A reason is required and audited (`67`).
- Signals: more than two reprints for one credential; an operator reprinting far above peers at the
  same desk; a `REPLACED` medium presented at a gate.

## Surface 8 — offline replay abuse

Offline doors decide locally and are reconciled later (`71`). Two abuses:

| Abuse | Detection |
|---|---|
| A revoked or cloned credential admitted at a disconnected door | At sync, reconciliation surfaces `is_offline_replay` rows whose credential was revoked before `occurred_at`, and clone pairs across devices — surfaced, never rewritten (`71`) |
| A compromised device key submitting fabricated scans — faking attendance or sponsor footfall | Scans outside the device's heartbeat windows; sustained rates impossible for a person (for example more than one scan per second for a minute); `occurred_at` far from `recorded_at` beyond measured clock skew (`40`); a device reporting scans at two access points |

Replaying the same queued scan is already a no-op (`client_generated_id UNIQUE`,
`AccessScanService.php:47-58`). Keys expire with the event and are revocable per device (`40`),
which bounds the damage of a stolen tablet to one event.

## False positives — the strategy

| Source of false positives | Mitigation |
|---|---|
| Adjacent lanes of one gate | Gate groups with transit 0 |
| Operator scans the same badge twice at two devices | Same gate group; de-duplicate within seconds |
| Offline device clock skew | Use skew measured from heartbeats (`40`); widen windows for `is_offline_replay` rows |
| Staff moving fast through back-of-house | Staff credentials evaluated with their own transit table, or excluded from impossible-travel |
| Bidirectional points with the wrong scan mode (`54`) | Double-presence suppressed where no exit point exists |
| Families sharing a card to buy tickets | Commerce signals are review-only, never blocking |

**Rollout:** every detector runs in **shadow mode** first — signals recorded, no alerts — for the
first operated events. Reviewers mark each signal `CONFIRMED` or `DISMISSED`; a detector is promoted
to alerting only when its measured precision is acceptable. The threshold is set after the pilot, not
guessed now. Nothing auto-revokes on a behavioural signal. Where a block is applied, the gate sees
"see supervisor", never "fraud".

## Model

```
fraud_signals
  id, short_id, account_id, event_id NULL,
  signal_type,        -- CREDENTIAL_IMPOSSIBLE_TRAVEL | DOUBLE_PRESENCE | REPLACED_CREDENTIAL_USED
                      -- | UID_REUSE | SUN_COUNTER_REPLAY | CREDENTIAL_PROBING | FORGERY_BURST
                      -- | REPRINT_ANOMALY | DEVICE_ANOMALY | PROMO_BRUTE_FORCE
                      -- | BULK_PURCHASE | PAYMENT_FAILURE_SPIKE
  severity,           -- LOW | MEDIUM | HIGH
  status,             -- OPEN | CONFIRMED | DISMISSED
  subject_type, subject_id,       -- CREDENTIAL | DEVICE | ACCESS_POINT | ORDER | PROMO_CODE | IP
  evidence jsonb,                 -- access_log ids, timings, transit used
  dedupe_key varchar(191),        -- type + subject + window: repeats attach, do not multiply
  occurrences int default 1, first_seen_at, last_seen_at,
  mode,                           -- SHADOW | ALERTING
  reviewed_by NULL → users, reviewed_at NULL, resolution_note NULL,
  incident_id NULL → incidents,   -- 60
  timestamps
  UNIQUE (dedupe_key) WHERE status = 'OPEN'
  INDEX (event_id, status, severity)
```

- **Grouped, like `53`'s alerts.** Two hundred denials at one gate are one open signal with
  `occurrences = 200`.
- **Never auto-closed.** A signal is dismissed by a person with a note.
- Detection runs as a queued job after each online `GRANTED` scan (one indexed query on
  `(credential_id, occurred_at DESC)`), in batch at sync for offline rows, and nightly for commerce.
- `fraud_signals` is profiling for a security purpose: retention follows security incidents in
  `65`, and reviews are audited (`67`).

## Migration

| Step | Change | Risk / When |
|---|---|---|
| 1 | FR1: no ticket codes in public list responses; scanner checks in by id | **Now** — two-sided but small |
| 2 | FR2, FR5: named limiters on order creation, payment intents and every code evaluation | **Now** |
| 3 | FR3: drop plaintext `credentials.identifier` (store an encrypted copy only if badge re-render needs it); `raw_identifier` kept only for unresolved scans — with ARZ-313's identifier format change | **Now** — tables empty |
| 4 | FR4: `access_logs.device_id` (`67` A3; ARZ-314, ARZ-321) | **Now** |
| 5 | FR8: ticket code opt-in in exports | Low |
| 6 | Code rotation on holder change (FR6, ARZ-327); `ticket_transfers` | Rotation soon; transfers with `16` |
| 7 | `fraud_signals`, `access_point_transit`, gate groups; detectors in shadow mode | Phase 4, with offline scanning |
| 8 | Reprint issues a new QR medium | With `36`'s `credential_media` and ARZ-073 |
| 9 | Promote detectors to alerting after the pilot | After measured precision |

## Open questions

- **Transfer policy** — on by default, with a cutoff? Per event; the default is a commercial choice.
- **Bot challenge provider** — a CAPTCHA adds third-party script and data flow (`65`, `66`). Leaning: only on flagged events.
- **Default transit time** between zones — 120 s is a guess until venue data exists.
- **Staff credentials** — excluded from impossible-travel, or a separate table? Staff lend badges too; leaning separate, stricter review.
- **Who reviews signals on event day?** A security role in the command center (`53`, `106`); unreviewed signals are noise.

## Related

`24-access-control.md` · `36-rfid-nfc.md` · `38-scanner-platform.md` · `40-device-management.md` ·
`21-badge-management.md` · `46-promo-codes.md` · `53-live-event-command-center.md` ·
`60-incident-management.md` · `64-security.md` · `65-privacy-gdpr.md` · `66-compliance.md` ·
`67-audit-logging.md` · `71-realtime-architecture.md` · `16-attendee-management.md` · `91-attendee-platform.md` ·
`14-waitlist-and-capacity.md` · `42-email-marketing.md` · `136-master-backlog.md`
