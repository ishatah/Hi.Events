# Waitlist and Capacity

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 11  
**Blocks:** 27


---

## Purpose

Capacity pools and waitlist offers.

## Current state

`CONFIRMED`: `waitlist_entries` with join/offer/expire plus mails and scheduled expiry jobs; `capacity_assignments` / `product_capacity_assignments` for shared pools.

## Key decisions

- Extend `waitlist_entries` with a nullable `session_id` for session waitlists rather than building a parallel system.
- `UNVERIFIED` whether the offer/expiry service is cleanly extensible or too event-coupled. Read it before committing (ARZ-081).

## Open questions

- Should zone capacity (`25`) reuse capacity assignments, or is occupancy-derived enforcement sufficient? `24` assumes the latter.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `11` · `27`
