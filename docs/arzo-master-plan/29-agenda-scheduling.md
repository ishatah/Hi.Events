# Agenda and Scheduling

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 27, 28  
**Blocks:** 30


---

## Purpose

Programme authoring, conflict handling, publication, calendar export.

## Current state

`MISSING`. `CONFIRMED`: `spatie/icalendar-generator` is already a dependency and `frontend/src/utilites/calendar.ts` generates event-level ICS — so session ICS is an extension, not new work.

## Key decisions

- Room double-booking enforced by a database GiST exclusion constraint, not application checks alone.
- Speaker clashes are hard errors; attendee clashes are warnings only.

## Open questions

- Multi-track agenda UI at phone width is hard. Needs a real design pass (`87`).
- Should a recurring event clone its programme per occurrence? Interacts with occurrence bulk generation.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `27` · `28` · `30`
