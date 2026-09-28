# Background Jobs

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 71


---

## Purpose

Queue architecture and reliability.

## Current state

`PARTIAL`, 70%. `CONFIRMED`: 27 jobs, per-job retry/backoff with no shared base policy, admin failed-jobs UI with retry, a scheduled failed-job count warning. Defects F7 and F13: queue lists differ across dev/prod/e2e, `occurrences_queue_name` has no default, and `webhook_queue_name` defaults to a *connection* name.

## Key decisions

- Fix the queue-name configuration before adding load; this is a config-armed production bug.
- Introduce a base job with a default retry/backoff policy so new jobs are safe by default.
- Badge rendering and sync reconciliation will add burst load — size workers before Phase 4, not during.

## Open questions

- Horizon for queue visibility? Not currently installed.
- Separate worker pools for latency-sensitive vs bulk work?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `71`
