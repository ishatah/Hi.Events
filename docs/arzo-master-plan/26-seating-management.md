# Seating

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 25  


---

## Purpose

Seat maps, allocation, and assignment.

## Current state

`MISSING`. No seat entity. `CONFIRMED`: the sole `seat` match in the codebase is a demo-data string.

## Key decisions

- Seats belong to rooms (`25`), created only for events that need reserved seating — most do not.
- Assume zone-level access control is sufficient; do not build per-seat access rules without a client requirement.

## Open questions

- Seat-map authoring is a substantial UI project. Buy a component or build?
- Does ARZO's actual event mix need reserved seating at all? If rarely, this drops in priority.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `25`
