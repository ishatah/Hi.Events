# Exhibitor Surface

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** New subsystem — magic-link portal + lead-capture PWA · **Priority:** P2 (ARZ-130, ARZ-132, ARZ-133, ARZ-134) · **Phase:** 3; real logins after ARZ-011/012
**Depends on:** `32-exhibitor-management.md`, `33-exhibitor-lead-capture.md`, `09-permissions-and-roles.md`, `65-privacy-gdpr.md`, `92-staff-platform.md`, `95-mobile-event-app.md`
**Blocks:** nothing directly

---

Exhibitors are third parties. They may see their own company, staff, booth and leads — and nothing
else. `32` modelled the participation and chose magic links for v1; `33` modelled leads and chose
capture-now-resolve-later on a PWA. This document decides the two surfaces those choices imply, what
each shows, and how long exhibitors keep access.

## Current state — `MISSING`, 0%

| Fact | Evidence |
|---|---|
| No exhibitor, company or lead code | Search for "exhibitor" in `backend/app` and `frontend/src`: no hits |
| No `companies`, `event_exhibitors`, `exhibitor_staff`, `lead_captures` or `leads` tables | Live DB, `to_regclass` |
| `exhibitor.manage` and `lead.view` are seeded; nothing reads them | Live DB `permissions` |
| **Account membership is account-wide**: event authorization compares `account_id` only | `IsAuthorizedService.php:70-77` at `e7228c1d` |
| The magic-link precedent: `ticket_lookup_tokens`, 24-hour expiry, **token stored in plaintext** | `SendTicketLookupEmailHandler.php:21,74-75` |
| A browser camera scanner exists and can be reused for capture | `CheckIn/tabs/InlineCameraScanner.tsx` |
| **The badge QR is a working access credential** — 40 random characters, stored with its SHA-256 | `CredentialIssuanceService.php:86-93` |

The last row changes `33`'s model (below). The fourth is why v1 cannot use accounts (`32` fact 1).

## Decision: two surfaces, one identity mechanism

| | **Portal** | **Lead capture** |
|---|---|---|
| Who | Exhibitor admins, mostly before the event, on a laptop | Booth staff, during the event, on their own phones |
| Where | `/exhibitor/:eventId/...` in the SSR app, online-only | `/m/leads`, the PWA prefix from `95`, offline-capable |
| Session purpose | `EXHIBITOR_PORTAL` | `LEAD_CAPTURE` |
| Holds personal data on the device | Only on screen | **No** — an outbox of hashes until sync (`33`) |

Both authenticate through `92`'s `person_login_links` exchanged for `person_sessions`, scoped to one
`event_exhibitors` row. **Purpose-scoped sessions are the point:** the phone left on a booth counter
holds a capture session that cannot list or export the company's leads.

## What each shows

| Capability | Portal, `ADMIN` | Portal, `STAFF` | Capture |
|---|---|---|---|
| Company listing and logo — rich text through `HtmlPurifierService` | Edit | View | — |
| Staff: name and remove within quota (creates and withdraws `EXHIBITOR` accreditations, `32`) | Yes | — | — |
| Staff badges: issued, printed, collected | Yes | Own | — |
| Booth: code, hall, size, status, floor plan, build-up and breakdown windows (`35`) | Yes | Yes | — |
| Deadlines and requested documents (`58`, `73`) | Yes | View | — |
| Contract and payment status (`32`: recorded, not transacted) | View | — | — |
| **Leads**: list, rate, notes, qualification, consent basis | All | Own captures | Own captures, resolution status only after sync |
| **Export** | Yes, after accepting data terms | — | — |
| Devices signed in, with revoke | Yes | — | — |

**Not shown, ever:** any other exhibitor's data, attendee lists, orders, visitor analytics beyond the
exhibitor's own leads.

## Design correction to `33`: hash the identifier on the phone

`33` stores `lead_captures.raw_identifier`. With the landed credential format, that is **every
visitor's working badge**, collected by a third party, sitting on their phones and in a table they can
read. A booth could print a visitor's badge and walk into a zone as them; anti-passback (ARZ-062)
detects that, it does not prevent it.

The fix costs almost nothing, because credentials are already looked up by hash
(`AccessScanService.php:72`):

```
lead_captures
  - raw_identifier
  + identifier_hash varchar(64)   -- SHA-256 computed on the phone (WebCrypto, HTTPS only)
credentials
  + legacy_identifier_hash NULL   -- SHA-256 of the upper-cased A- public_id, set by the
                                  -- ARZ-053 backfill; indexed; also feeds 94's device roster
```

Legacy `A-` codes are ~36 bits and their hashes are brute-forceable; they were never a strong
credential. Credential identifiers (over 200 bits) are not.

## Lead capture — the flow

1. Staff member opens `/m/leads`; the capture session is on the device.
2. Scan the badge with the camera. The phone hashes the payload and drops the plaintext.
3. Rating (hot, warm, cold) and a note — the only fields staff reliably fill in at a busy booth (`33`).
4. Saved to the outbox with a `client_generated_id` at once; "Pending" until sync.
5. On sync the server resolves and returns the status. The list shows names **only while online**, from
   the server; nothing personal is written to IndexedDB.

| Resolution (`33`) | Capture screen | Portal |
|---|---|---|
| `RESOLVED` | Name, company, job title | The `shared_fields` snapshot |
| `NO_CONSENT` | "This visitor has not agreed to share their details. Your visit is counted." | Counted, no row |
| `UNKNOWN_IDENTIFIER` | "Not an event badge" | — |
| `OTHER_EVENT` | "Badge from another event" | — |

The note is the exhibitor's own text and may itself contain personal data; it leaves the outbox when
the server confirms receipt.

## Consent display

- Each lead in the portal shows its basis, from `65`'s consent record: "Shared under the
  lead-sharing consent given at registration on <date>", and exactly the fields transferred.
- **Consent withdrawn after capture:** ARZO redacts its copy and shows the withdrawal date. Earlier
  exports cannot be recalled — the exhibitor is the controller of that copy (`33`), and the export
  dialog says so before the first export.
- **Export requires accepting the exhibitor data terms**, recorded per person with version and time.
  The terms are a legal deliverable (`65`, `66`), `UNVERIFIED` until drafted; export does not ship
  without them.
- Every export is logged: who, when, which fields, how many leads (`67`).

## Access windows and retention

| Access | Opens | Closes |
|---|---|---|
| Magic link | Sent | 72 hours or its use limit — a proposal |
| Portal session | `event_exhibitors` reaches `APPROVED` | Event end **+ 90 days** — `33`'s proposal; the policy is `UNVERIFIED` until `65` and legal set it |
| Capture session | `build_up_starts_at` (`56`) | `breakdown_ends_at` (`56`) |
| Lead data in ARZO | Capture | End of the portal window, then deleted; two reminder emails before |

Cancelling an exhibitor (`32`) revokes every session at once.

## v2 — real logins

After ARZ-011/012/013, an `EXHIBITOR` role (`09`) bound to one `event_exhibitors` row. Returning
companies (`companies` is account-scoped, `32`) can then keep one login across editions. **Only the
authentication changes**: the portal's API resolves a "current exhibitor" from either principal, so
v1 screens carry over.

## Scope out

Ordering stand services, taking payment (`32`), chat with attendees (`31` — deliberately not built),
dedicated rented lead-retrieval devices (`33`), business-card scanning. Meeting booking (ARZ-210)
will add an exhibitor side to this portal later.

## Release, telemetry, tests

| Concern | Approach |
|---|---|
| Release | The SSR app's pipeline; capture shares `/m/sw.js` (`95`); `123`'s freeze |
| Telemetry | Captures per hour per exhibitor, unresolved share, sync lag — aggregated, feeding booth traffic (`54`) and sponsor evidence (`34`) |
| Isolation tests | Cross-exhibitor refusal suite on every portal endpoint, in the style of ARZ-006 |
| E2E | Magic link → portal shows only own company (`116` exit 6); offline capture → sync → resolves (`116` exit 8, Playwright `setOffline`); no-consent capture yields no personal data |

## Build order

| Stage | Content | Backlog |
|---|---|---|
| X1 | Portal on magic links: listing, staff within quota, booth view | ARZ-130, ARZ-132, ARZ-131 |
| X2 | Capture PWA with hashed identifiers, outbox and resolution | ARZ-133 |
| X3 | Portal leads: consent basis, rating, notes, export behind accepted terms | ARZ-133 |
| X4 | Qualification questions; rules-based score that shows its reasons | ARZ-134 |
| X5 | Real logins with the `EXHIBITOR` role | after ARZ-011, ARZ-012 — unnumbered |
| X6 | Exhibitor-owned `lead.captured` webhooks | `49` open question |

X2 needs `95`'s `/m/` service worker. Whichever of ARZ-133 and ARZ-150 lands first builds it.

## Open questions

- **Per-exhibitor opt-in in the attendee app** (`33`)? Presenting the badge is the choice today; the app could make it explicit. A consent-design question for `65`.
- **Android NFC capture** via Web NFC for HF badges (`36`)? iPhone cannot (`36`). Later, if tags are procured.
- **Exhibitor staff on iPhone with no install** — capture works in the browser tab; offline survives only while the tab lives. Acceptable, or push the Home Screen install? Leaning: push the install at the staff briefing.
- **Arabic portal** — depends on the exhibitor base (`82`).

## Related

`32-exhibitor-management.md` · `33-exhibitor-lead-capture.md` · `09-permissions-and-roles.md` ·
`92-staff-platform.md` · `95-mobile-event-app.md` · `65-privacy-gdpr.md` · `35-booth-management.md` ·
`34-sponsor-management.md` · `38-scanner-platform.md` · `49-webhooks.md` · `54-attendance-intelligence.md` ·
`56-event-operations.md` · `67-audit-logging.md` · `116-phase-3.md`
