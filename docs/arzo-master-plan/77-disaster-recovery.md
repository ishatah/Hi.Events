# Disaster Recovery

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 76  
**Blocks:** 126


---

## Purpose

Recovering from serious failure.

## Current state

`UNVERIFIED`. Backups are presumably provider-managed (Vapor/RDS, managed Postgres); no documented RPO/RTO, no tested restore found.

## Key decisions

- An untested backup is not a backup. Restore must be rehearsed, and the rehearsal recorded.
- Event-day DR is different from normal DR: there is no maintenance window, and the devices hold recent state that can help reconstruct.

## Open questions

- RPO and RTO targets — currently undefined.
- Could a venue keep operating from device-local data through a total backend outage? `71` suggests partially yes; worth designing deliberately.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `126` · `76`
