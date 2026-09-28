# Performance Testing Plan

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 74, 75  


---

## Purpose

Proving the performance targets.

## Current state

`MISSING`. No load testing evidence found.

## Key decisions

- Test the arrival spike, not the average: thousands of attendees through a handful of gates in thirty minutes.
- The derived occupancy query and the access decision path are the two things most likely to fail under load (`24`, `74`).

## Open questions

- Target event profile for load tests — currently unknown, and needed before any target is meaningful.
- Load-testing tooling and where it runs.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `74` · `75`
