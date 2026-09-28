# QA Strategy

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 79  
**Blocks:** 129


---

## Purpose

Process around the testing in `79`.

## Current state

`PARTIAL`: strong backend and E2E discipline; **zero** frontend tests and no lint/typecheck in CI.

## Key decisions

- Quality gates in CI, not in review comments (`129`).
- On-site features need field testing with real hardware and real staff — no amount of CI substitutes.

## Open questions

- Is there a dedicated QA role, or is it engineer-owned?
- Who signs off event-readiness (`59`)?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `129` · `79`
