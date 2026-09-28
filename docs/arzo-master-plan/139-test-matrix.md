# Test Traceability Matrix

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 79, 138  


---

## Purpose

Requirement to implementation to test to evidence.

## Current state

`MISSING` as a matrix. Test coverage itself is `PARTIAL` — strong backend services, 73 E2E specs, zero frontend tests, and only one check-in spec covering the search path.

## Key decisions

- One row per requirement: implementation reference, test reference, evidence, release.
- The gaps this will make visible first: authorization (7 of 268 Actions tested), offline behaviour (untestable today), hardware paths (no fakes yet).

## Open questions

- Generated or hand-maintained? Hand-maintained matrices are abandoned within two quarters.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `138` · `79`
