# Engineering Work Packages

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 136, 128  


---

## Purpose

The template and index for executable work packages.

## Current state

New. Packages are written when an item is picked up, not all up front — a package written six months early is fiction.

## Key decisions

- Each package carries: objective, current state with evidence, target state, dependencies, database/backend/frontend/API/infrastructure changes, security requirements, testing requirements, migration requirements, rollout and rollback strategy, acceptance criteria, definition of done with any waivers.
- A package is ready when an engineer can execute it without guessing. If it needs guessing, it is not ready.
- First packages to write are the Phase 0 items in `136` — they are small, independent, and reduce live risk.

## Open questions

- Where do packages live — here, or in the issue tracker? Duplication guarantees drift; the tracker is probably right, with this document as the template.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `128` · `136`
