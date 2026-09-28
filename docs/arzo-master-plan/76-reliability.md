# Reliability

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 71, 78  
**Blocks:** 77


---

## Purpose

Availability targets and failure behaviour.

## Current state

`PARTIAL`: healthcheck is a static 200 at `/up` that does **not** check database, Redis, or queue — it will report healthy with a dead database. Startup aborts on failed migration (good). Job retries are per-job and inconsistent.

## Key decisions

- Event-day availability is the only target that matters; a ticketing outage is lost revenue, a gate outage is a crowd-safety issue.
- Offline capability (`71`) is the primary reliability mechanism, not redundancy.
- Replace the static healthcheck with real dependency checks and a separate readiness probe.

## Open questions

- What availability commitment does ARZO make to clients (`101`)?
- Is there an on-call rotation? Reliability engineering without one is aspirational.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `71` · `77` · `78`
