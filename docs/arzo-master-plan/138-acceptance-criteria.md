# Reusable Acceptance Criteria

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 128  
**Blocks:** 139


---

## Purpose

Criteria patterns reused across features.

## Current state

New.

## Key decisions

- Reusable sets for: CRUD with tenancy, an authorized endpoint, an offline-capable write, a hardware-dependent action, a scheduled job, a notification.
- The offline-write set is the most important and least obvious: idempotent under replay, no silent loss, degraded-mode disclosure, reconciliation surfaced not hidden.

## Open questions

- Keep as prose, or as executable test templates? Executable is better and more work.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `128` · `139`
