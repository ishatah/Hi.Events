# Monitoring and Alerting

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 78  


---

## Purpose

What is watched and who is told.

## Current state

`PARTIAL`: Sentry alerting on errors. No metric-based alerting, no on-call defined.

## Key decisions

- Event-day alerting differs from normal: device offline, print queue stalled, sync lag rising, denial-rate spike. These need a human watching (`53`).
- Alert on symptoms attendees feel, not on infrastructure noise.

## Open questions

- On-call rotation during events — who, and with what authority to act?
- Alert routing: same channel for platform and event-day issues, or separate?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `78`
