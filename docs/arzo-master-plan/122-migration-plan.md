# Data Migration Plan

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 07  


---

## Purpose

Moving data safely as the model evolves.

## Current state

New. Most Phase 1 work is additive, which is the main risk control.

## Key decisions

- Every migration is additive first, backfilled second, cut over third, cleaned up last — and reversible until cutover.
- The one genuinely risky migration is check-in consolidation (F10, `18`): dual-write, backfill, move reads, then stop writing the old path. At no point before the final step should turning the new system off lose data.
- Backfills that create credentials for every existing attendee (ARZ-053) must be idempotent and restartable.

## Open questions

- Backfill volume — how many historical attendees? Determines whether backfills need batching.
- Do we ever drop `attendee_check_ins`? Deferred; it is the rollback path.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `07`
