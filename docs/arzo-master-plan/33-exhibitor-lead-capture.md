# Exhibitor Lead Capture

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem · **Priority:** P2 (ARZ-133, ARZ-134) · **Phase:** 3
**Depends on:** `32-exhibitor-management.md`, `38-scanner-platform.md`, `65-privacy-gdpr.md`, `71-realtime-architecture.md`
**Blocks:** `34-sponsor-management.md` (sponsor evidence), `51-reporting.md` (exhibitor reports)

---

## Current state — `MISSING`

`CONFIRMED`: no lead, capture or qualification entity. `lead.view` is a seeded permission string
that nothing reads (`2026_09_29_000007:31`).

What exists that the design must account for:

| Fact | Evidence | Why it matters |
|---|---|---|
| The ticket QR is the bare attendee `public_id`, format `A-XXXXXXX` | `AttendeeTicket/index.tsx:151-156`; `IdHelper.php:32-35` | Before badges exist, this is what an exhibitor would scan |
| `public_id` is random, ~36 bits after upper-casing, with **no unique index** | Live DB indexes on `attendees` | Resolution must scope to the event, never global |
| Marketing opt-in exists at **order** level only | `orders.opted_into_marketing_at`, `event_settings.show_marketing_opt_in` | It is consent to the *organizer's* marketing — not consent to share data with exhibitors |
| A `CHECKBOX` question can act as consent, but produces no timestamped, purpose-bound record | `QuestionTypeEnum`; `question_answers.answer` jsonb | Adequate for a code of conduct; weak evidence for a data transfer |
| The public check-in search matches on **email**, though email is not returned | `AttendeeRepository.php:130` | An existing enumeration oracle (`38`, `65`) — lead lookup must not repeat it |

## What a lead is

An exhibitor staff member scans an attendee's badge at the booth. The result is **the attendee's
contact details transferred to the exhibitor**, plus the exhibitor's notes about the conversation.

That makes lead capture a **data transfer from one controller to another**, not a check-in. The
design follows from that sentence.

## Decision: a lead scan is not an access scan

The earlier scaffold said lead capture "reuses the scan path". Only half of that holds.

| Reused | Not reused |
|---|---|
| Identifier recognition and resolution (`38`) | The access decision (`24`) — there is nothing to grant |
| The offline idempotency pattern (`client_generated_id`) | `access_logs` — a booth visit is not zone entry, and writing it there would corrupt occupancy and attendance (`54`) |

Booth traffic analytics derive from lead captures, not from access logs.

## Consent — the legal centre of the feature

Under Qatar PDPL and GDPR the exhibitor becomes a separate controller the moment details transfer.
The defensible model:

1. **Disclosure and opt-in at registration**: "Exhibitors you allow to scan your badge will receive
   your name, company, job title and email." Recorded as a purpose-bound consent record —
   `(event_id, person_id, purpose = EXHIBITOR_LEAD_SHARING, granted_at, withdrawn_at, source)`,
   schema owned by `65`.
2. **The physical act of presenting the badge** is the per-exhibitor choice. Industry practice
   treats it as such; the registration disclosure is what makes that practice defensible.
3. **No consent → no personal data.** The capture is still recorded, so booth traffic counts stay
   true, but the exhibitor sees "This attendee has not agreed to share their details".

The shared field set is **minimal and configured per event**: name, company, job title, email by
default. **Never** ID document, date of birth, nationality, phone or question answers unless the
organizer opts a field in and the disclosure names it.

## Design: capture now, resolve later

The insight that makes this cheap: **a lead device does not need the attendee roster.**

A scanner at a door must decide offline, so it carries the roster (`71`). A lead scanner only
*records* — the raw identifier, the time, and the staff member's notes — and resolution to a
person happens on sync. So:

- **No personal data at rest on the device** until sync returns it
- Therefore **a PWA is acceptable** for lead capture, unlike door scanning, where `30` and `94`
  conclude native is needed for at-rest encryption
- Offline in an exhibition hall — the worst connectivity on site — costs nothing but a delay in
  seeing the name

Exhibitor staff use their own phones, signed in through the tokenized link from `32`. Rented
dedicated lead-retrieval devices are out of scope unless a client demands them.

## Model

Two tables, mirroring the `access_logs` / `access_grants` split: an append-only record of what
happened, and a mutable working record derived from it.

```
lead_captures                    -- append-only evidence, never updated
  id, event_id, event_exhibitor_id, captured_by_exhibitor_staff_id,
  identifier_hash, identifier_type,       -- hashed on the phone (93); QR | RFID | NFC | MANUAL
  captured_at timestamptz,                -- device clock
  recorded_at timestamptz,                -- server clock
  client_generated_id uuid UNIQUE,
  resolution,     -- RESOLVED | NO_CONSENT | UNKNOWN_IDENTIFIER | OTHER_EVENT
  lead_id NULL → leads,
  metadata jsonb, created_at
  INDEX (event_exhibitor_id, captured_at)

leads                            -- one per exhibitor per person
  id, short_id, event_id, event_exhibitor_id, person_id,
  shared_fields jsonb,           -- exactly what was transferred, at capture time
  first_captured_at, last_captured_at, capture_count int,
  rating NULL,                   -- HOT | WARM | COLD
  notes NULL, qualification jsonb,
  status,                        -- NEW | CONTACTED | QUALIFIED | DISQUALIFIED
  exported_at NULL, timestamps, deleted_at
  UNIQUE (event_exhibitor_id, person_id) WHERE deleted_at IS NULL
```

The identifier is stored **hashed, never raw** (`93`): a badge QR is a working access credential, and a
booth phone's queue of raw codes would be a bag of admissions if the phone were lost.

`shared_fields` is the accountability record: the exhibitor's view reads from it, not from the live
`persons` row. What the exhibitor holds is then exactly what was transferred, provable after the
fact, and unaffected by later profile edits.

A rescan of the same attendee by the same exhibitor appends a capture and updates
`last_captured_at` and `capture_count` — it does not create a second lead.

## Qualification

| Option | Verdict |
|---|---|
| Rating (hot/warm/cold) + free notes | **v1.** Fast at a busy booth; the only thing staff reliably fill in. |
| Up to five exhibitor-defined questions | v2, stored in `qualification` |
| Organizer-defined event-wide schema | Rejected — exhibitors' questions differ by product |

## Delivery

| Channel | When |
|---|---|
| Portal list, filter, rate, annotate | v1 (`32` portal) |
| CSV/XLSX export | v1 — the existing `app/Exports` pattern |
| Webhook `lead.captured` to the exhibitor's CRM | v2 — needs exhibitor-owned webhook subscriptions; today webhooks belong to organizers and events (`49`) |

## Scoring — ARZ-134, ARZ-241

`133` A2 applies: start **rules-based and explainable** — rating, capture count, sessions attended
in the exhibitor's category, qualification answers. An exhibitor must be able to see *why* a lead
scored high. Learned scoring (ARZ-241) needs lead outcomes that will not exist for several events.

## Retention and deletion

- Exhibitor access to leads in the platform ends a fixed period after the event (proposed 90 days)
- A deletion request from an attendee removes ARZO's copy. **Once an exhibitor has exported, ARZO
  cannot recall the data** — that copy is the exhibitor's responsibility as controller. This must be
  stated in exhibitor terms and in the attendee disclosure, not discovered during a complaint.

## Open questions

- **Consent granularity.** One consent for all exhibitors, or per-exhibitor opt-in in the app? The badge-presentation model assumes the former; the app makes the latter possible.
- **Exhibitor terms of use** — who drafts the data-processing terms exhibitors accept before they can export? A legal deliverable, not software (`65`, `66`).
- **Retention period** — 90 days is a proposal, not a policy.
- **Should attendees see which exhibitors scanned them?** Transparency that builds trust, and a notification cost. Leaning yes, in the app (`30`).
- **Business-card capture** for attendees without badges — out of scope unless requested.

## Related

`32-exhibitor-management.md` · `34-sponsor-management.md` · `38-scanner-platform.md` ·
`51-reporting.md` · `54-attendance-intelligence.md` · `65-privacy-gdpr.md` ·
`71-realtime-architecture.md` · `133-ai-capabilities.md`
