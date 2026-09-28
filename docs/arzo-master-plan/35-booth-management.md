# Booth Management

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 25, 32  


---

## Purpose

Booths as space, assigned to exhibitors.

## Current state

`MISSING`. Modelled in `25` because a booth is a place before it is a commercial unit.

## Key decisions

- `booths` lives in the space model, referenced by the exhibitor module.
- Booth assignment is a join with a status lifecycle (available, held, assigned, built).

## Open questions

- Floor-plan authoring for booth layout — same tooling question as seat maps (`26`).
- Does booth assignment need to drive access grants (exhibitor staff may enter their own booth zone early)?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `25` · `32`
