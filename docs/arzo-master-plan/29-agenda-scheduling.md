# Agenda and Scheduling

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** New subsystem · **Phase:** 3
**Depends on:** `27-sessions-tracks.md`, `28-speakers-management.md` · **Blocks:** `30-mobile-event-app.md`

---

## Current state — schema ready, no behaviour

`CONFIRMED` from Phase 1:

- `sessions` with `starts_at` / `ends_at` as **`timestamptz`**, plus `track_id`, `room_id`, `capacity`
- A **GiST exclusion constraint** rejecting two published sessions sharing a room at overlapping times
- A CHECK constraint requiring `ends_at > starts_at`
- `tracks`, `speakers`, `session_speakers`
- Verified by test: overlaps rejected, back-to-back allowed, draft overlaps allowed

Also `CONFIRMED`: `spatie/icalendar-generator` is already a dependency and
`frontend/src/utilites/calendar.ts` generates event-level ICS — so session ICS is an **extension of
existing code**, not new work.

## Three conflict classes, three different responses

Getting these severities right is most of the design:

| Conflict | Response | Enforced by |
|---|---|---|
| Two published sessions, same room, overlapping | **Hard error** | Database (GiST) — done |
| One speaker in two overlapping sessions | **Hard error**, overridable with a reason | Application (`28`) |
| An attendee registered for overlapping sessions | **Warning only** | Application |

The attendee case must not block: people legitimately register for both and decide later. Treating it
as an error would be the most annoying possible behaviour.

## Authoring UI

The hard part is not the data, it is the interface.

A multi-track agenda is a two-dimensional grid — time down, track or room across — and it does not
survive naive responsiveness. At phone width a grid becomes unusable, so the attendee view needs a
genuinely different layout (a chronological list with track labels), not a scaled-down grid.

`87` owns the design pass. Flagging it here because "make the table responsive" is the wrong instinct
and will be the first thing attempted.

Organizer-side needs: drag to reschedule, duplicate a session, bulk-shift a day when the schedule
slips, and an obvious display of the conflicts above at the point of editing rather than on save.

## Publication

`sessions.is_published` already exists, and the GiST constraint deliberately applies **only to
published sessions** — so an organizer can draft an overlapping alternative and publish the one they
choose. That is the intended workflow, not a loophole.

A published agenda change mid-event needs a notification path (`43`, `44`): a room change nobody is
told about is worse than no agenda.

## Calendar export

Three levels, all cheap given the existing dependency:

1. **Single session** ICS
2. **Personal agenda** — the sessions an attendee registered for
3. **Full event** agenda subscription feed

The personal feed is the valuable one, and it should be a **subscribable URL** rather than a one-time
download, so schedule changes propagate to the attendee's calendar. That implies a tokenised,
revocable feed URL — the `ticket_lookup_tokens` pattern applies.

## Timezone handling

`sessions` uses `timestamptz` deliberately, diverging from the existing `timestamp without time zone`
convention (recorded in `121`). The reason is exactly this document: an agenda displayed in the wrong
timezone is the most common and most embarrassing event-software bug, and naive timestamps make it
likely.

Display rule: **venue local time by default**, with the timezone named explicitly whenever the
viewer's device timezone differs.

## Open questions

- **Multi-track phone layout** — needs a real design pass (`87`), not a CSS decision.
- **Should a recurring event clone its programme per occurrence?** Likely yes, and it interacts with occurrence bulk generation. Deferred in `27`.
- **Bulk reschedule semantics** — if a day slips 30 minutes, do registrations, waitlist offers and notifications all follow? Probably, and it is more work than the drag interaction.
- **Agenda versioning** — does an organizer need to see what changed and when? `67` has the audit pattern.

## Related

`27-sessions-tracks.md` · `28-speakers-management.md` · `30-mobile-event-app.md` ·
`43-sms-notifications.md` · `44-push-notifications.md` · `87-ui-ux-system.md` · `121-technical-debt.md`
