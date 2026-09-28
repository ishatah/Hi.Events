# Release Strategy

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 83, 85  


---

## Purpose

How work is released.

## Current state

`CONFIRMED`: trunk-ish flow with `develop` and `main`, release-tagged multi-arch images, Vapor plus DigitalOcean deploys.

## Key decisions

- Feature flags for anything touching check-in or access — these cannot be rolled back after an event has started.
- **Event-aware freeze windows**: no production deploys while a client event is live. This is a hard requirement specific to this domain.

## Open questions

- Flag tooling — build, or adopt?
- Who authorizes an emergency deploy during an event?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `83` · `85`
