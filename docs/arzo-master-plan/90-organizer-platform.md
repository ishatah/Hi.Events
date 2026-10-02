# Organizer Surface

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep (pages) + Replace (navigation) · **Priority:** P1 — the restructure must precede the first new page; per-event visibility rides ARZ-012 · **Phase:** 1, before any Phase 2 surface ships
**Depends on:** `09-permissions-and-roles.md`, `87-ui-ux-system.md`
**Blocks:** the organizer UI of `23-accreditation.md`, `25-zones-and-permissions.md`, `27-sessions-tracks.md`, `32-exhibitor-management.md`, `40-device-management.md`, `56-event-operations.md`

---

The main authenticated experience: accounts, organizers and events. For commerce it is mature. It
is also about to receive roughly as many new pages as it has today, and its navigation — a flat,
headed list — will not survive that. This document decides the information architecture and how
per-event roles shape what a user sees.

## Current state — `CONFIRMED`, mature for commerce; no home for anything new

### Routes

`router.tsx` defines **88 paths**. 47 are the authenticated management area; removing the four
layout parents leaves **43 pages**, the scaffold's figure:

| Area | Paths | Evidence |
|---|---|---|
| `/manage` — events list, account, profile, email confirmations | 6 | `router.tsx:78-121` |
| `/account` — settings, taxes and fees, event defaults, users, danger zone | 7 | `:241-292` |
| `/manage/organizer/:organizerId?` — dashboard, events, settings, homepage designer, webhooks, locations, payments, reports | 10 | `:295-365` |
| `/manage/event/:eventId` | 24 | `:368-533` |

The event area has 23 child routes and **21 distinct pages**: the index duplicates `dashboard`, and
`getting-started` redirects to it (`:389-392`). Event Settings alone holds 11 sections plus platform
fees (`routes/event/Settings/index.tsx:47-112`).

### Navigation

| Fact | Evidence |
|---|---|
| The sidebar renders a flat list; a heading is an item with no link | `AppLayout/Sidebar/index.tsx:27-78` |
| Event sidebar: 18 links under 5 headings — Overview, Setup & Design, Ticketing & Sales, Guest Management, Integrations; 16 visible at once, because occurrences and capacity management exclude each other by event type | `layouts/Event/index.tsx:96-148`, `showWhen` at `:115,139` |
| Organizer sidebar: 7 page links under 5 headings, plus two conditional entries (switch organizer; set up payouts); one heading is untranslated (`label: 'Overview'`) | `layouts/OrganizerLayout/index.tsx:73-116`, `:90` |
| **`NavItem.showWhen` already exists** — the hook for permission-based hiding — and is used only for event type and organizer count | `AppLayout/types.ts:13` |
| No permission model in the frontend: 4 components check "is account admin", nothing checks anything per event | search `useIsCurrentUserAdmin` |
| Backend: 43 permissions seeded, `permission_roles` empty on dev, `event_users` 0 rows, nothing reads them | live DB; ARZ-011, ARZ-012 TODO |
| E2E navigates mostly by URL — 57 `page.goto()` calls; link-role lookups in 5 files | `e2e/**` |

### The new subsystems have no frontend

Search for `venues`, `accreditation`, `credential`, `sessions` or `speakers` in `frontend/src` finds
only copy text. Uncommitted and in progress at audit time: **50 backend endpoints** — venues with
zones, access points and rooms (account-scoped, under `/venues`), and event-scoped tracks, speakers,
sessions, accreditation types, access rules, access logs, access scans and credentials.

**A naming collision is already live.** The organizer "Locations" page describes its contents as
"Reusable venues" (`routes/organizer/Locations/index.tsx:30`; `LocationsTable/index.tsx:89`), while
the new `venues` table is a space model that *references* a location through `venues.location_id`
(`25`). Ship a Venues page beside it unchanged and users meet two things called venues.

| # | Defect | Evidence | Severity |
|---|---|---|---|
| N1 | "Venue" already means "address" in the interface; the new space model will collide with it | above | Medium, the day a Venues page ships |
| N2 | Organizer sidebar heading `Overview` bypasses Lingui | `OrganizerLayout/index.tsx:90` | Low — **fix now** |
| N3 | Navigation cannot express permission-based visibility except through ad-hoc `showWhen` closures | `AppLayout/types.ts:4-15` | Not live — no per-event roles exist yet — but blocks ARZ-012's interface |

## The problem: the surface roughly doubles

| Scope | New surface | Source |
|---|---|---|
| Library (account/organizer) | Venues — floors, zones, access points, rooms | `25` |
| Library | Companies | `32` |
| Library | Devices and printers — registry and enrolment | `40`, `39` |
| Library | Badge templates, reusable across events | `22` |
| Event | Programme — agenda, sessions, speakers, tracks | `27`–`29` |
| Event | Accreditation — types, application queue | `23` |
| Event | Credentials and badges — issue, revoke, print jobs | `21`, `22` |
| Event | Access — the event's zones, rules, simulator, access log | `24`, `25` |
| Event | Exhibitors, booths, sponsors, leads | `32`–`35` |
| Event | Devices assigned to access points | `40` |
| Event | Operations — timeline and gates, tasks, readiness, staffing, incidents | `56`–`60` |
| Event | Live command center | `53` |

About fourteen new event pages on top of 21, and four library pages. A flat sidebar of 30-plus
links is the point at which users stop reading it and start searching for URLs.

## Decision: group by job, disclose by module and permission

Four principles, each for a reason:

1. **Group by the job and when it happens, not by entity.** Planners think "selling", "programme",
   "getting the site ready", "running it", "what happened" — in roughly that order across an
   event's life. Entity groupings ("Guest Management") scatter one job across sections.
2. **A section appears only when its module is on for the event and the user holds a permission
   inside it.** A ticketing-only event seen by its owner keeps today's navigation, almost unchanged.
   This protects the self-serve customer — whoever the buyer turns out to be (`04`) — from ARZO's
   operational depth.
3. **Library objects live above the event; events reference them.** Venues, companies, devices and
   badge templates are reused. The event page shows *this event's use* of them and links out.
4. **URLs do not change.** `/manage/event/:eventId/products` stays where it is. The restructure moves
   links between groups; bookmarks, support articles and the 57 URL-driven E2E navigations keep
   working.

### Proposed event navigation

```
Overview                Dashboard · Readiness (Operations on)
Sell                    Tickets & products · Orders · Attendees · Messages · Promo codes
                        · Affiliates · Waitlist · Capacity | Dates (recurring)
                        · Exhibitors & sponsors (module)
Programme  (module)     Agenda · Sessions · Speakers · Tracks
On-site    (module)     Accreditation · Credentials & badges · Access · Check-in
                        · Devices & printers
Operations (module)     Timeline & gates · Tasks · Staffing · Incidents · Live
Insights                Reports · Access log · Attendance
Settings                Event settings · Registration questions · Homepage · Ticket & badge design
                        · Widget · Webhooks
```

| Section | Why it is its own group |
|---|---|
| **Sell** | Everything a ticketing customer does today, minus design and integration. Exhibitors sit here because an exhibitor is a commercial relationship first (`32`); their staff passes flow into On-site's accreditation queue |
| **Programme** | Time within the event (`27`). Separate from Sell because a free conference still has a programme |
| **On-site** | Who may go where, and the equipment that enforces it. Accreditation is here rather than beside attendees because its output is access, not a sale |
| **Operations** | Only for events ARZO operates — shown when an `event_operations` row exists (`56`); a self-serve event never sees it |
| **Insights** | Reports and logs, labelled provisional while devices are unsynced (`52`) |
| **Settings** | Configure-once pages, moved to the bottom so daily work sits at the top |

Where today's 18 links go: Dashboard → Overview; Reports → Insights; Occurrence schedule → Sell
(dates are what is sold); Event Settings, Homepage Designer, Ticket Designer, Registration Questions,
Widget Embed, Webhooks → Settings; everything else → Sell. Check-In Lists → On-site › Check-in, and
merges into access points once check-in is consolidated (ARZ-041).

### Modules

| Module | How it turns on |
|---|---|
| Programme, On-site, Exhibitors | An explicit "add capability" action in Settings — discoverable without cluttering the sidebar |
| Operations | Inferred: the event has an `event_operations` row (`56`) |

A section with a module on but no content yet shows its empty state, not nothing — the user just
asked for it.

### Venues and Locations — one library entry

**Merge in the interface.** One "Venues" page in the library; a venue has an address, which is the
existing `locations` row, and optionally spaces. A ticketing customer's venue is an address and
nothing more — today's Location, renamed. `locations` and `event_locations` stay as they are
underneath (`25`). Two address books with overlapping names is the one outcome to avoid.

## Decision: hide what a user cannot do, and still enforce on the server

Per-event roles (`09`, ARZ-012) make a CHECKIN_OPERATOR on event A a stranger to event B, and an
ACCREDITATION_OFFICER a stranger to refunds. The UI must reflect that before the user clicks, not
after a 403.

| Layer | Behaviour |
|---|---|
| Effective permissions | One server call returns the permission strings for (user, event); cached per event in React Query. New endpoint — unnumbered, part of ARZ-012 |
| Navigation | Each item declares the permissions that make it visible (any of); `showWhen` evaluates them |
| Routes | A page opened by URL without permission renders "You don't have access to this part of the event — ask *owner name*", never a form that fails on submit |
| Actions | Buttons for refund, revoke, approve, delete are **hidden** without the permission. Disable-with-explanation only when the permission exists and the object's state forbids the action |
| Event list | Shows only events where the user holds a role — filtered by the server, not the browser |

**Hiding is presentation, not security.** Every endpoint still authorizes (`09`'s three layers); a
hidden button that the API would accept is a defect.

Most event-day roles should never see this surface: stewards work in the scanner app (`94`), the
badge desk in its own screen, exhibitors in their portal (`93`). The organizer surface is for
people who plan.

## Event-day behaviour

From doors-open until close — the `LIVE_OPS` stage where one exists (`56`), otherwise the event's
own dates — the event's landing page becomes the live summary (`53`), and Operations › Live is
pinned at the top of the sidebar. The rest of the navigation does not reorder: muscle memory
matters more on the busiest day than a clever layout.

## Migration

| Step | Change | When |
|---|---|---|
| 1 | Nav model: sections, module flag, permission list per item; collapsible groups | Phase 1, before the first new page |
| 2 | Regroup today's 18 links into the new sections — **no new pages** — and ship alone, so users learn the structure with familiar content | Phase 1 |
| 3 | Effective-permissions endpoint, `useEventPermissions`, route guard page, server-filtered event list | With ARZ-012 |
| 4 | Venues/Locations merge in the interface | With the first venue page |
| 5 | Module toggles; each domain adds its pages into its section | Phases 2–5 |
| 6 | Event-day landing | With `53` |

Step 2 is cheap and reversible; it should not wait for any new capability.

## Open questions

- **Explicit or inferred modules?** Leaning explicit for Programme, On-site and Exhibitors; inferred for Operations. Inferring from data ("has sessions") hides a module until its first record exists, which makes it hard to find.
- **Does the organizer layer earn its place for ARZO-operated events?** ARZO's own events may all sit under one organizer. Keep it — self-serve tenants need it — but default to the single organizer rather than asking.
- **Should library items be account-wide or per organizer?** `venues.organizer_id` is nullable (`25`). Show account-wide items in every organizer's library; filter only when an organizer is set.
- **Search as navigation?** At 35-plus pages, a command palette ("go to Accreditation") earns its keep. Defer until the regrouping has been used.

## Related

`09-permissions-and-roles.md` · `87-ui-ux-system.md` · `25-zones-and-permissions.md` ·
`23-accreditation.md` · `27-sessions-tracks.md` · `32-exhibitor-management.md` ·
`40-device-management.md` · `53-live-event-command-center.md` · `56-event-operations.md` ·
`92-staff-platform.md` · `93-exhibitor-platform.md` · `94-mobile-scanner.md`
