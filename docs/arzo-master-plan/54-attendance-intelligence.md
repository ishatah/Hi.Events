# Attendance Intelligence

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 24, 27, 52  
**Blocks:** 55


---

## Purpose

Turning scan data into attendance insight.

## Current state

`MISSING` beyond basic check-in counts.

## Key decisions

- Everything here derives from `access_logs` IN/OUT pairs and `session_attendance` — which is exactly why those tables are append-only with direction (`24`, `27`).
- Dwell time, no-show rate, arrival curves, peak occupancy, session popularity all become derivable.

## Open questions

- No-show definition: registered and never scanned? Needs a policy per event type.
- Privacy: individual movement tracking across zones is sensitive. Aggregate by default (`65`).

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `24` · `27` · `52` · `55`
