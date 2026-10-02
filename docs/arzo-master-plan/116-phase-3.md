# Phase 3 — Programme, Exhibitors, Attendee App

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d` · **Complexity:** L
**Classification:** Derived (from `113`, `136`) · **Prerequisite:** Phase 1 complete. **Parallel with Phase 2.**
**Depends on:** `114-phase-1.md`, `27-sessions-tracks.md`, `29-agenda-scheduling.md`, `32-exhibitor-management.md`, `30-mobile-event-app.md`, `48-api-platform.md`
**Blocks:** `118-phase-5.md`

---

## Objective

Turn the time model into a working programme, give exhibitors a portal and lead capture, and put an
app in attendees' hands. This is the phase that makes ARZO usable for conferences and exhibitions
rather than only ticketed events.

It depends on Phase 1, not on Phase 2 — the main scheduling flexibility in the roadmap. One
exception: exhibitor staff passes are `EXHIBITOR` accreditations (`32`), so ARZ-132 needs ARZ-051
from Phase 2.

## Where it stands

| Area | State at `e7228c1d` |
|---|---|
| Programme schema | `sessions` (GiST room-overlap, `timestamptz`), `tracks`, `speakers`, `session_speakers`, `session_products`, `session_registrations`, `session_attendance` — **done** |
| Session waitlist | `session_waitlist_entries` — **created as a sibling table** in `c34f6a59`, per `14`'s reversal |
| Programme CRUD | Sessions, speakers, tracks, rooms — uncommitted, in progress at audit time |
| Booths | Table exists, **wrong-shaped for per-event allocation** (`35`) |
| Exhibitors, companies, leads, sponsors | Nothing |
| RSVP | `invitations`, `rsvp_responses` created (`c34f6a59`); no behaviour (`12`) |
| Attendee app | Nothing — and the installable-but-blank manifest trap remains (`30`) |
| API keys, webhook versioning | Nothing; webhook retries currently never run (`49` W1) |

## Decisions carried in from the 13–60 documents

These change backlog items as originally written:

| Item | Original | Now |
|---|---|---|
| ARZ-081 session waitlist | Extend `waitlist_entries` | **Sibling table** — already created; one shared offer/expiry policy service over two repositories (`14`) |
| ARZ-082 session attendance | `IN`/`OUT` | **`ENTRY`/`EXIT`** — change `session_attendance.direction`'s default before the first row (`54`) |
| ARZ-130 exhibitor entity | One `exhibitors` table + logins | `companies` + `event_exhibitors` + `exhibitor_staff`; **magic-link portal** until per-resource RBAC (`32`) |
| ARZ-131 booth assignment | Status on `booths` | `booths.event_id` + `booth_assignments`; `booths.status` becomes physical state only (`35`) |
| ARZ-132 staff passes | Direct credential source | **`EXHIBITOR` accreditation** within quota — no change to the credential CHECK (`32`) |
| ARZ-133 lead capture | "Reuses the scan path" | Reuses **identifier resolution** only; `lead_captures` + `leads`; capture-now-resolve-later; consent record from `65` (`33`) |
| ARZ-140 SMS | Provider integration | **Phone capture first** — the frontend cannot even create a `PHONE` question (`43`) |
| ARZ-150 attendee app | "PWA or native?" | **PWA** inside the existing app, service worker + IndexedDB, scoped `networkMode` change (`30`, `95`) |

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-080 Session registration + capacity | `27` | New |
| ARZ-081 Session waitlist (sibling table) | `14` | New |
| ARZ-082 Session attendance + session check-in | `27`, `54` | New |
| ARZ-083 Agenda UI + conflict detection | `29` | New |
| ARZ-084 Session and personal-agenda ICS (subscribable, tokenized) | `29` | Extend |
| ARZ-130 Companies, exhibitors, magic-link portal | `32`, `93` | New |
| ARZ-131 Booth correction + assignment | `35` | Fix + New |
| ARZ-132 Exhibitor staff passes via accreditation | `32` | New |
| ARZ-133 Lead capture + export | `33` | New |
| ARZ-134 Lead qualification + rules-based scoring | `33` | New |
| Sponsor listing — `sponsorships` + public logo strip (unnumbered) | `34` | New |
| ARZ-190 RSVP flow on the landed tables | `12` | New |
| ARZ-150 Attendee PWA — ticket, agenda, notifications | `30`, `95` | New |
| ARZ-151 Venue map (image-based) | `95`, `35` | New |
| ARZ-140 Phone capture + SMS/WhatsApp provider | `43` | New |
| ARZ-141 Push — web push first; staff native push with Phase 4 apps | `44` | New |
| ARZ-090 API keys + scopes | `48` | New |
| ARZ-091 Versioned webhook payloads **and working retries** | `49` | Fix + Refactor |

Sponsor fulfilment evidence (`34`) and networking (`31`) are Phase 5; eRaffle (ARZ-211) may be pulled
forward into late Phase 3 because it needs only `access_logs`.

## Exit criteria

| # | Criterion |
|---|---|
| 1 | An attendee registers for a limited session, is waitlisted when full, and is offered a place on cancellation — through the shared offer policy |
| 2 | Session check-in records `ENTRY` and `EXIT`; dwell time is derivable where exits are scanned |
| 3 | Two published sessions cannot share a room at overlapping times — database-enforced (**met** by the GiST constraint) |
| 4 | A speaker clash is refused unless overridden with a reason; an attendee clash only warns |
| 5 | A personal agenda feed subscribes in a calendar client and reflects a later room change |
| 6 | An exhibitor admin opens their portal from a magic link and sees only their own company, staff and leads |
| 7 | Naming a staff member within quota yields an `EXHIBITOR` credential without organizer action; beyond quota it waits for approval |
| 8 | A lead captured **offline** on a phone resolves on sync; an attendee without lead-sharing consent yields a capture with no personal data |
| 9 | Leads export with qualification and the `shared_fields` snapshot |
| 10 | Two events at one venue can allocate the same booth code independently |
| 11 | The attendee PWA shows ticket and personal agenda **with no network**; the manifest has `start_url` and `scope` |
| 12 | A session room change reaches registrants by push where subscribed and by SMS/WhatsApp or email otherwise |
| 13 | An API key with a read-only scope is refused on a write endpoint |
| 14 | A webhook to an endpoint that is down for two minutes is delivered after it recovers |
| 15 | A webhook payload does not change when an unrelated API resource is refactored (contract test) |

Criterion 14 is new: before `49`'s fix, it fails — retries never run.

## Non-engineering gates

- Lead-sharing disclosure wording and exhibitor data terms (`33`, `65`, `66`) — legal deliverables
- SMS sender ID or WhatsApp business verification — lead time, not code (`43`)

## Out of scope

- Native scanner and kiosk apps, offline lead capture hardening beyond the PWA queue (Phase 4)
- Networking, meetings, polls, Q&A (Phase 5; eRaffle possibly earlier)
- Sponsor entitlement evidence reporting (Phase 5)
- Learned lead scoring (ARZ-241) — needs outcome data
- Exhibitor logins as platform users — after per-resource RBAC

## Risks

| Risk | Mitigation |
|---|---|
| Multi-track agenda at phone width | A chronological list with track labels, not a shrunken grid — design pass first (`29`, `87`) |
| iOS web push reaches few attendees | Measure at the pilot; operational messages never depend on push alone (`44`) |
| Lead capture is a third-party data transfer | Purpose-bound consent record; minimal shared fields; exhibitor terms (`33`, `65`) |
| Exhibitor portal leaks account data | Magic-link scope to one `event_exhibitors` row; never `account_users` membership (`32`) |
| Booth correction slips and code writes the wrong shape | Land the `booths` change before any booth service (`35`) |

## Related

`113-roadmap.md` · `115-phase-2.md` · `117-phase-4.md` · `118-phase-5.md` · `27-sessions-tracks.md` ·
`29-agenda-scheduling.md` · `30-mobile-event-app.md` · `32-exhibitor-management.md` ·
`33-exhibitor-lead-capture.md` · `35-booth-management.md` · `43-sms-notifications.md` ·
`48-api-platform.md` · `49-webhooks.md` · `95-mobile-event-app.md` · `136-master-backlog.md`
