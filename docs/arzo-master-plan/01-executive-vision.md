# Executive Vision

**Status:** WRITTEN · **Authority:** Authoritative for scope and non-goals · **Audit date:** 2026-09-28

---

## Where ARZO is

ARZO runs a rebranded fork of Hi.Events: a mature self-serve **ticketing and registration
platform**. `CONFIRMED` by audit — 72 database tables, 266 API endpoints, 88 frontend routes,
268 HTTP actions, 1,215 passing unit tests.

It does one thing well. An organizer creates an event, defines ticket types, publishes a branded
page or embeds a checkout widget, takes Stripe payments, emails QR-coded tickets, and scans those
QRs at the door. Around that core sits genuinely strong commerce: tiered and early-bird pricing,
promo codes, waitlists, shared capacity pools, affiliate attribution, invoices with VAT, recurring
events, multi-organizer tenancy, GDPR deletion workflows, webhooks, and 20 locales.

## Where ARZO is going

> A platform that runs an event end to end — from the first sales lead to the post-event
> report — covering the attendee journey, the on-site operation, the exhibitor, the staff, and
> the hardware at the door.

The gap is not a list of missing features. It is two missing **dimensions**.

## The two missing dimensions

This is the central architectural finding of the audit, and the reason this plan is shaped the
way it is.

### Space — `MISSING`

`CONFIRMED`: no `zone`, `booth`, `seat`, `room`, `floor`, `building`, `entrance`, or
`access_point` entity exists anywhere in the schema or domain objects. Searches for these terms
return only incidental matches — "zone" appears solely as *time*zone, "venue" only as re*venue*.

The closest existing entity is `locations`, which is a **flat geocoded address**: `name`,
`structured_address` (jsonb), `latitude`, `longitude`, Google Places provider fields. No
hierarchy, no interior subdivision, no notion of a controlled area.

An event is therefore a point on a map. It has no inside.

### Time-within-event — `MISSING`

`CONFIRMED`: no `session`, `track`, `speaker`, `agenda`, or `timeslot` entity exists.

`event_occurrences` looks like it might serve, and does not: it models **RRULE repeats of the
whole event**. A weekly class has 52 occurrences; a one-day conference with 40 talks has one
occurrence and no way to express the 40 talks.

An event therefore has a start and an end, but no interior structure.

### Why this is the whole story

Nearly every gap traces back to one or both:

| Capability | Blocked by |
|---|---|
| Zone-based access control | Space |
| Seating | Space |
| Exhibitor booths & lead capture | Space |
| Anti-passback, re-entry rules | Space (+ a schema constraint, below) |
| Session check-in & attendance | Time |
| Agenda, speakers, tracks | Time |
| Attendee mobile app (agenda is its spine) | Time |
| Session-level capacity & waitlists | Time |
| Per-session analytics | Time |

Adding features without these dimensions means bespoke workarounds that later have to be undone.
Adding the dimensions first makes each feature a normal build. **This is the single most important
sequencing decision in the plan.**

### A third finding, narrower but sharp

`CONFIRMED` by live query: `attendee_check_ins` carries

```sql
CREATE UNIQUE INDEX attendee_check_ins_unique_attendee_list
  ON attendee_check_ins (attendee_id, check_in_list_id)
  WHERE deleted_at IS NULL;
```

The schema **structurally forbids** scanning the same attendee twice against the same list. Also,
`attendees.checked_in_at` is a single scalar column.

Re-entry, anti-passback, and multi-point access are therefore not merely absent — they are
actively prevented by a uniqueness constraint. Access control must introduce a new append-only
`access_logs` table; it cannot extend `attendee_check_ins`. Planning that assumed otherwise would
have hit this in the first sprint. See `24-access-control.md`.

## What ARZO is committing to

Per direction taken 2026-09-28: **plan the full platform, software at full depth, sequenced
honestly.**

That last word governs. This plan names, per phase, where a capability needs money and people
rather than code — badge printers, RFID encoders, kiosk enclosures, trained registration staff.
Software plans for those are real; the operational commitment behind them is a business decision
this document does not pretend to make.

### In scope

- Every software capability across all 141 documents
- Space and time domain models as first-class architecture
- Accreditation, badges, access control, sessions, exhibitors
- Hardware **abstraction layers** — vendor-swappable adapters for scanners, printers, RFID/NFC, kiosks
- Offline-first on-site operation
- Public API platform, CRM integrations, SMS/push
- Command center, attendance intelligence
- Staffing, tasks, incidents, vendors, procurement as software
- AI capabilities, assessed for feasibility rather than assumed

### Out of scope for engineering

These are business commitments, planned as specifications, not built:

- Purchasing and owning hardware inventory (`102`, `103`)
- Recruiting and managing on-site staff (`57` plans the software; the agency is not code)
- Badge stock — lanyards, holders, holograms
- IT consultancy as a service line

### Explicit non-goals

Named so they don't accrete by default:

- **Not** a rewrite. The commerce core is sound and stays. Every change is classified Extend / Refactor / Replace / New subsystem, with justification.
- **Not** Evento feature-matching for its own sake. Parity where it serves ARZO's events; skip what doesn't.
- **Not** a general-purpose venue CMS, marketing suite, or accounting system.
- **Not** abandoning self-serve ticketing. It funds the rest and serves real users.

## The AGPL constraint

`CONFIRMED`: the codebase is AGPL-3.0 and §7(b) requires retaining "Powered by Hi.Events"
attribution on all pages and emails. ARZO currently satisfies this via the permitted rephrasing
("Powered by ARZO, based on Hi.Events", linking `hi.events`).

Two consequences that must not be discovered late:

1. **Network-use copyleft.** Running modified AGPL software as a network service obliges offering corresponding source to users of that service. Every subsystem in this plan built inside this codebase inherits that obligation.
2. **Commercial licensing is a decision, not an afterthought.** If ARZO intends to sell this as closed SaaS, get legal advice on a commercial licence from Hi.Events **before** Phase 2, not after five phases of proprietary work are entangled with AGPL code.

`UNVERIFIED`: whether ARZO has taken legal advice on this. Flagged in `120-risk-register.md` as a
top risk because it is cheap to resolve now and expensive later.

## Maturity assessment

Scored per the `128-definition-of-done.md` standard, where 100% requires database, backend,
frontend, validation, authorization, error handling, testing, security, observability,
documentation, and operational readiness.

| Domain | Maturity | Note |
|---|---|---|
| Ticketing & commerce | **90%** | Production-proven. Stripe + offline only. |
| Registration | **85%** | Strong. No RSVP-as-distinct-flow. |
| Attendee management | **80%** | Solid CRUD, export, self-service edit |
| Email messaging | **70%** | Templates, scheduling, 5 fixed audiences. No segmentation. |
| Check-in | **45%** | Real scanner. No offline, kiosk, or multi-point. |
| Reporting | **40%** | 4 report types, all revenue |
| Multi-tenancy | **60%** | Account/organizer scoping. `UNVERIFIED` isolation rigour — see `08`. |
| Permissions | **25%** | 3 roles, no granularity, no per-event roles |
| On-site operations | **10%** | QR scanning only |
| Access control | **5%** | Product-scoped lists, not zones |
| Public API | **5%** | Table exists, nothing uses it |
| Badges | **0%** | Prints tickets, not badges |
| Accreditation | **0%** | |
| Sessions / programme | **0%** | |
| Exhibitors | **0%** | |
| Attendee app | **0%** | No PWA, no native |
| Hardware | **0%** | |
| Realtime | **0%** | Stock Laravel stub, nothing wired |
| Staffing / ops | **0%** | |

**Weighted:** ARZO is a high-quality implementation of roughly one third of the target platform.

## Success criteria

The plan has worked when ARZO can, for a real multi-day multi-zone event:

1. Take the event from lead through contract to closeout in one system
2. Register attendees online and at the door, including walk-ins
3. Issue accreditation by type with approval workflows
4. Print photo badges on demand, and reprint after failure
5. Enforce zone and time access rules at multiple points, with anti-passback
6. **Keep working when the venue network drops** — and reconcile cleanly on reconnect
7. Run a session programme with per-session check-in and capacity
8. Give exhibitors lead capture and give organizers lead analytics
9. Show a live command center that is the event-day source of truth
10. Produce post-event reporting on attendance, sessions, leads, and operations
11. Expose all of it through an authenticated public API

Criterion 6 is the one that separates an event platform from a ticketing website, and the one
most likely to be underestimated. See `71-realtime-architecture.md`.

## Related

`02-current-state-audit.md` (evidence) · `03-gap-analysis.md` (the delta) ·
`113-roadmap.md` (sequence) · `120-risk-register.md` (risks incl. licensing) ·
`128-definition-of-done.md` (the standard scored above)
