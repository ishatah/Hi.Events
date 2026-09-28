# Organizer Surface

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 09, 87  


---

## Purpose

The main authenticated experience.

## Current state

`CONFIRMED` mature: 43 routes across account, organizer, and event management.

## Key decisions

- New subsystems add surfaces here: venues/zones, programme, accreditation review, badges, exhibitors, devices. Navigation will not survive naive addition — it needs restructuring in Phase 1.
- Per-event roles (`09`) mean the UI must hide what a user cannot do, not just reject it on submit.

## Open questions

- Information architecture for roughly double the current surface count?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `09` · `87`
