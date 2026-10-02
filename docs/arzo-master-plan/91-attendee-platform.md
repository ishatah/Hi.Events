# Attendee Surface

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep (commerce) + Extend (identity, programme, accreditation) · **Priority:** P2 (ARZ-080, ARZ-083, ARZ-084, ARZ-150); T1–T4 unnumbered — add to `136` when scheduled · **Phase:** fixes now; attendee sessions before Phase 2's accreditation portal; programme in Phase 3
**Depends on:** `27-sessions-tracks.md`, `23-accreditation.md`, `87-ui-ux-system.md`
**Blocks:** `95-mobile-event-app.md`

---

What attendees see on the web: the event page, checkout, their tickets, and — once the new
subsystems exist — accreditation applications, session registration and a personal agenda. The
commerce half is good. The identity model underneath it is a set of unrelated bearer links, which
is fine for buying a ticket and not enough for anything that follows a person through an event.

## Current state — `CONFIRMED`, ~75% for commerce; 0% for programme and accreditation

| Surface | Route | Access | Evidence |
|---|---|---|---|
| Organizer page | `/events/:organizerId/:organizerSlug` (+ `/past-events`) | Public | `router.tsx:536-552` |
| Event page | `/e/:eventId/:eventSlug` and `/event/:eventId/:eventSlug` — the same `EventHomepage` | Public | `:554-584`; `layouts/PublicEvent/index.tsx:16-21` |
| Checkout | `/checkout/:eventId/:orderShortId/` → `details` → `payment` → `summary`, plus `payment_return` | Order short id (`o_` + 13 random) is the bearer | `router.tsx:594-630`; `IdHelper.php:27-30` |
| Embedded widget | `/widget/:eventId` + `embed/widget.js` (440 lines, Shadow DOM, origin-checked `postMessage`) | Public | `02` |
| Ticket | `/product/:eventId/:attendeeShortId` | Attendee short id is the bearer; returns name, email and `public_id` — the QR payload | `router.tsx:656`; `AttendeeResourcePublic.php:28-47` |
| Print | `/order/…/print`, `/product/…/print` | Same bearers; `window.print()` (F8) | `router.tsx:632-646` |
| My tickets | `/my-tickets/:token` | Emailed on request; 24 hours; multi-use; one live token per email; lists completed orders **across every organizer** for that address | `SendTicketLookupEmailHandler.php:21,57-78`; `GetOrdersByLookupTokenHandler.php:43-72` |
| Self-service | Edit order or attendee name and email; resend confirmation or ticket; throttled; on by default | Order + attendee short ids | `api.php:671-678` (baseline); `allow_attendee_self_edit = true` |

The ticket email is a **link**, not a ticket: a button to `/product/%d/%s` (`config/app.php:61`;
`AttendeeTicketMail.php:87-91`) and an ICS attachment (`:141`). No email template contains a QR
(search `qr` in `resources/views/emails` → 0), and there is no wallet pass (search `pkpass`,
`wallet` → 0). The only place the code exists is a page that needs the network — no service worker
(`30`) — behind a white overlay until JavaScript loads (`87` U5).

The way back to one's tickets is on the **staff login page**: the "find my tickets" form lives in
`routes/auth/Login/index.tsx:27-114`.

## Defects

| # | Defect | Evidence | Severity |
|---|---|---|---|
| T1 | **No offline ticket.** The QR exists only on a network-dependent page; the email carries a link | above | **High on event day** — attendees arrive when the venue network is worst (`30`) |
| T2 | **Self-service email change works as a ticket transfer but leaves the old QR valid.** Changing the attendee email rotates the ticket link's short id, not the `public_id` the QR encodes; any screenshot or printout from the previous holder still scans | `SelfServiceEditAttendeeService.php:59-75` | Medium — `68` |
| T3 | Ticket lookup tokens are stored in plaintext and matched by equality; the email address is logged when no orders match | `SendTicketLookupEmailHandler.php:41-45,67-78`; `GetOrdersByLookupTokenHandler.php:56` | Low |
| T4 | The public attendee endpoint ignores its `event_id` — the short id alone selects the attendee, under any event id | `GetAttendeeActionPublic.php:30-55` | Low — the short id is the secret |
| T5 | Every page declares `lang="en"`; hooks after an early return on the event page | `87` U1, U7 | Medium / Low |
| T6 | "Find my tickets" lives on the organizer login page | above | Low — findability |

**Fix now, independent of the roadmap:** T2 (rotate the `public_id` on email change and tell the
organizer that a transfer happened), T3 (store a SHA-256 hash, as `CredentialIssuanceService`
does, and stop logging the address), T4, and T6 (a "find my tickets" link on every event and order
page).

## Decision: passwordless attendee sessions, not accounts

`30` left open whether attendees need accounts. Three options:

| Option | Gains | Costs |
|---|---|---|
| Bearer links only (today) | No friction | Every capability needs its own URL-as-credential; nothing follows the person across devices; revocation is per link; a personal agenda has nowhere to live |
| Password accounts | Durable identity | Password resets dominate support; a credential-stuffing surface; friction at the one moment that makes money |
| **Passwordless session on a verified email** | One identity for tickets, agenda, registrations, accreditation status and push subscriptions — still no password | Email delivery becomes login-critical; a session layer to build |

**Recommendation: the third.** The ticket-lookup link already proves control of an email address; it
is a login that forgets itself. Let it mint a session instead of being one:

- **Checkout stays guest.** Nobody signs in to buy. "Keep my tickets on this device" is offered on
  the order summary.
- Sign-in is **required** only where identity carries weight: accreditation applications (status
  tracking, document upload), registration for capacity-limited sessions (one hold per person), and
  a personal agenda synced across devices.
- **A separate guard.** An attendee session must never satisfy `auth:api`; it is a different
  principal from a staff user (`09`).
- Sign-in links are single-use and stored hashed. The session is an httpOnly cookie, so the
  credential is not in a URL that gets forwarded.

The identity it resolves to is `persons` (`23`), which is **account-scoped**
(`persons.account_id`, indexed with email but not unique). A session is therefore a verified email
at platform level that reaches, per tenant, the persons with that address — the same cross-organizer
reach the ticket lookup already has, and the buyer's own data.

Why not accounts: an attendee's relationship is with the event, not the platform. A password is a
cost the attendee pays so that we can say they have an account.

## Where the new capabilities plug in

| Capability | Entry point | Identity | Offline | Phase |
|---|---|---|---|---|
| **Accreditation application** | A per-type application page, open or by invitation (`invitations` exists) | Session required; status page shows applied → approved → credential issued | No | 2 |
| **Session registration** | "Register" on a session in the agenda; overlap is a warning, never a block (`29`); full sessions fall to the waitlist (ARZ-081) | Ticket holder — `session_registrations` is keyed on `attendee_id` (`27`) | Queued, reconciled (`30`) | 3 |
| **Personal agenda** | Registrations plus starred sessions, on the event page and in the app; subscribable ICS feed with a revocable token (`29`, ARZ-084) | Stars work anonymously on the device; sync needs a session | **Required** (`30`) | 3 |
| **Ticket wallet** | The same agenda screen, ticket first | Session or ticket link | **Required** | 3 (ARZ-150) |

Two hard gates:

- **No identity documents until they are encrypted.** An accreditation form that asks for passport
  numbers must not ship while `persons.id_document_number` and `date_of_birth` are plaintext text
  columns with no cast (`23`, `65`). Photos and documents go to private storage only.
- **Self-service edits lock after accreditation.** Once a person holds an approved accreditation or
  a printed badge, a name change is a request to the organizer, not a silent edit — otherwise the
  badge and the record disagree (`21`).

### The offline ticket, before the app

The PWA (`30`, `95`) is Phase 3. Until then, the cheapest offline ticket there is: **put the QR in
the ticket email** as an inline image, beside the link. Email clients keep messages offline. A
forwarded email is a forwarded ticket, exactly as the link is today, and revocation is enforced at
the scanner's deny list rather than on the attendee's device (`30` open question).

Wallet passes are deferred: an Apple pass needs a Pass Type ID certificate from Apple's developer
programme, and Google Wallet an issuer account — the cost and upkeep are `UNVERIFIED`, and the PWA
covers the same need.

After the credential backfill (ARZ-053), the QR carries the credential identifier rather than the
`A-` code (`38`).

## Arabic and right-to-left: attendee surfaces first

Whether Arabic is required for ARZO's own events is an open business question (`04`). **If it is
required anywhere, it is required here first**: organizers and staff can be trained or equipped;
attendees cannot. `82` agrees.

| Work | Scope | Evidence |
|---|---|---|
| `ar` locale in Lingui and `backend/lang/` | Strings | 20 locales today, none Arabic |
| `lang` and `dir` set on `<html>` at SSR | Every page | `87` U1 |
| Mantine `DirectionProvider`; logical CSS properties | 49 physical-direction declarations in attendee styles: organizer page 16, event page 11, ticket 11, checkout pages 6, my tickets 2, widget and product styles 3 | search |
| An Arabic typeface | SF Pro has none | `88` D2 |
| Emails, invoice and ticket PDFs | Backend templates | `15`, `22` (shaping) |
| The widget | Direction set **inside** its iframe; it cannot inherit from the host page | `embed/widget.js` |

Organizer-entered content — titles, descriptions, session names — is single-language today.
Bilingual content fields are a separate and larger decision (below).

## Layout rules specific to this surface

From `87`'s attendee class: designed at 360px first; the primary action within thumb reach; the
ticket screen shows a large QR with its quiet zone, the name and the ticket type, readable by a
steward at arm's length; the agenda at phone width is a chronological list with track labels and a
pinned "now / next", never the desktop grid (`29`).

## Migration

| Step | Change | When |
|---|---|---|
| 1 | T2, T3, T4, T6; `lang` from the locale | **Now** |
| 2 | QR image in the ticket email (T1, interim) | **Now** — small |
| 3 | Attendee sessions: hashed single-use sign-in links, separate guard, `persons` resolution | Before the accreditation portal |
| 4 | Accreditation application and status pages | Phase 2, after identity-document encryption |
| 5 | Agenda, session registration, personal agenda, ICS feed | Phase 3 — ARZ-080, ARZ-083, ARZ-084 |
| 6 | PWA: offline ticket and agenda | Phase 3 — ARZ-150, `95` |
| 7 | Arabic and RTL on every surface above | When `ar` is scheduled (`82`) |

## Open questions

- **Session lifetime?** Through the end of the event the attendee last bought for is the natural default; how many devices at once is a fraud question for `68`.
- **Should organizers see that a buyer also attends other organizers' events?** No — the cross-organizer view is the buyer's alone, as it is today.
- **Bilingual event content?** Two title and description fields per event, or a translation table? Decide with `82`; it touches every organizer form.
- **Transfers as a feature?** T2 shows self-service already transfers tickets informally. Either make it an explicit, audited transfer (`68`) or restrict email edits to corrections before the event. `30` put transfer out of the app's scope; the web surface still needs an answer.
- **Does the widget carry the agenda?** No — the widget sells; the agenda lives on the event page and in the app.

## Related

`30-mobile-event-app.md` · `95-mobile-event-app.md` · `23-accreditation.md` ·
`27-sessions-tracks.md` · `29-agenda-scheduling.md` · `38-scanner-platform.md` ·
`68-fraud-prevention.md` · `65-privacy-gdpr.md` · `82-localization.md` · `87-ui-ux-system.md` ·
`88-design-system.md` · `09-permissions-and-roles.md`
