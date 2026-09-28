# Dependency Map

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 136, 113  


---

## Purpose

The full dependency graph across work items.

## Current state

Derived from `136-master-backlog.md` `Depends on` columns and the spine in `00`.

## Key decisions

- Two hard edges dominate: access control after space; offline after hardware abstraction.
- Phases 2 and 3 are parallel — both depend on Phase 1, neither on each other. That is the main scheduling flexibility available.

## Open questions

- Should this be generated from the backlog rather than maintained by hand? Hand-maintained graphs drift.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `113` · `136`
