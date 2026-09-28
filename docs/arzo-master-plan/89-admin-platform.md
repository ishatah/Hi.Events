# Platform Admin Surface

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 09  


---

## Purpose

ARZO staff administering the platform itself.

## Current state

`CONFIRMED` good: 13 admin routes — accounts, users, events, orders, attribution, configurations, failed jobs, messages, spam events, announcements, deletion requests, impersonation.

## Key decisions

- Extend for new subsystems: device fleet, accreditation oversight, credential revocation.
- SUPERADMIN is enforced **per action**, not at the route group — a new admin action that forgets the check is exposed (F12). Fix via the Phase 0 architecture test.

## Open questions

- Should platform admin be a separate deployment for blast-radius reduction?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `09`
