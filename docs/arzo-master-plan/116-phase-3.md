# Phase 116 — Programme, Exhibitors, Attendee App

**Status:** SCAFFOLD · **Audit date:** 2026-09-28 · **Complexity:** L
**Prerequisite:** Phase 1 complete. **Parallel with Phase 2** — depends on Phase 1, not on Phase 2

---

## Objective

Turn the time model into a working programme, give exhibitors lead capture, and put an app in attendees' hands. This is the phase that makes ARZO usable for conferences and exhibitions rather than only ticketed events.

It is parallelizable with Phase 2 if capacity allows — the main scheduling flexibility in the whole roadmap.

## Scope

| Item | Doc | Classification |
|---|---|---|
| ARZ-080 Session registration + capacity | `27` | New |
| ARZ-081 Session waitlist — extend `waitlist_entries` | `14` | Extend |
| ARZ-082 Session attendance + session check-in | `27` | New |
| ARZ-083 Agenda UI + conflict detection | `29` | New |
| ARZ-084 Session + agenda ICS export | `29` | Extend |
| ARZ-130 Exhibitor entity + portal | `32` | New |
| ARZ-131 Booth assignment | `35` | New |
| ARZ-132 Exhibitor staff passes | `32` | New |
| ARZ-133 Lead capture + export | `33` | New |
| ARZ-134 Lead qualification + scoring (rules-based) | `33` | New |
| ARZ-150 Attendee app — ticket, agenda, notifications | `95` | New |
| ARZ-151 Venue map | `95` | New |
| ARZ-140 SMS provider integration | `43` | New |
| ARZ-141 Push notification infrastructure | `44` | New |
| ARZ-090 API keys + scopes | `48` | New |
| ARZ-091 Versioned webhook payload boundary | `49` | Refactor |

## Exit criteria

| # | Criterion |
|---|---|
| 1 | An attendee registers for a limited session, is waitlisted when full, and is offered a place on cancellation |
| 2 | Session check-in records IN and OUT, and dwell time is derivable |
| 3 | Two published sessions cannot be saved in the same room at overlapping times (database-enforced) |
| 4 | A speaker clash is refused; an attendee clash warns only |
| 5 | An attendee downloads a personal agenda as ICS and it opens correctly in a calendar client |
| 6 | An exhibitor logs into their own portal and sees only their own leads |
| 7 | An exhibitor scans an attendee badge and the lead appears with consent recorded |
| 8 | Leads export with qualification data |
| 9 | The attendee app shows a ticket and agenda **with no network** (cached) |
| 10 | A push notification reaches the app for a session change |
| 11 | An API key with a read-only scope is refused on a write endpoint |
| 12 | A webhook payload does not change when an unrelated API resource is refactored (contract test) |

## Out of scope

- Offline lead capture beyond basic caching (Phase 4 hardens it)
- Networking, polls, Q&A, eRaffle (Phase 5)
- AI lead scoring — rules-based only here; learning needs outcome data
- Sponsor packages and entitlement tracking (Phase 5)
- Native scanner app (Phase 4)

## Risks

| Risk | Mitigation |
|---|---|
| Waitlist extension may be too event-coupled | `UNVERIFIED` — read the offer/expiry service before committing (ARZ-081) |
| Multi-track agenda UI at phone width is hard | Needs a real design pass (`87`) before build |
| App store review cycles add unpredictable delay | Decide PWA vs native early (`30` open question) |
| Lead capture is a third-party data transfer | Consent at scan time; DPIA review (`65`) |
| Exhibitor portal is the first real test of per-resource permissions | Verify `09` supports it before starting `32` |

## Status of this document

SCAFFOLD. Scope, exits and risks are captured from the audit. Expand into work packages
(`137-engineering-work-packages.md`) when the phase is scheduled — a package written
several phases early is fiction.

## Related

`113-roadmap.md` · `117-phase-4.md` · `27-sessions-tracks.md` · `32-exhibitor-management.md` · `30-mobile-event-app.md` · `136-master-backlog.md` · `128-definition-of-done.md` · `120-risk-register.md`
