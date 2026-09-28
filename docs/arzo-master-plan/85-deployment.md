# Deployment

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 83  
**Blocks:** 123


---

## Purpose

How changes reach production safely.

## Current state

`CONFIRMED`: Vapor deploy on `main`/`develop`, DigitalOcean polled up to 15 minutes, migrations run at container start with abort-on-failure in all-in-one.

## Key decisions

- **Never deploy during an event.** Needs an enforced freeze window tied to event schedules — a uniquely event-platform requirement.
- Device app versions must be deployable independently and support staged rollout (`40`).

## Open questions

- Blue/green or canary for the backend?
- How are offline device apps updated mid-event, if at all?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `123` · `83`
