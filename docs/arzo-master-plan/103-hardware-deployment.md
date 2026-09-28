# Hardware Deployment

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 102, 40  
**Blocks:** 107


---

## Purpose

Getting devices to a venue and working.

## Current state

**Out of engineering scope**, except the software that supports it (`40`).

## Key decisions

- Devices are staged, imaged, enrolled and pre-synced **before** leaving base — never configured at the venue under time pressure.
- Full roster pre-sync takes time (`71` targets under 60s for 10k); plan for it in the schedule.
- Asset tracking, charging, and a documented teardown are part of the process.

## Open questions

- Who owns device readiness — engineering or operations?
- How are devices sanitized of attendee data after an event (`65`)?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `102` · `107` · `40`
