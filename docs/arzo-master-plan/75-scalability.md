# Scalability

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 74  


---

## Purpose

How the system grows with event size and count.

## Current state

`UNVERIFIED`. No load testing evidence found. Vapor (Lambda) scales the backend horizontally by default; Postgres is the shared bottleneck.

## Key decisions

- Scale is driven by concurrent scans at gates, not by total attendees — a 10k-attendee event arriving over 30 minutes is the load spike.
- Offline-first is itself a scalability strategy: devices absorb the burst and reconcile.

## Open questions

- Expected peak: attendees, gates, scans per minute? Needed for `74` and to size anything.
- Does the derived occupancy query survive peak, or is the snapshot cache mandatory from day one?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `74`
