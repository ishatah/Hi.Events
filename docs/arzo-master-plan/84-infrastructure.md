# Infrastructure

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 65, 71  
**Blocks:** 85, 104


---

## Purpose

Where and how it runs.

## Current state

`CONFIRMED`: backend on Laravel Vapor (AWS Lambda, eu-west-1), frontend on DigitalOcean App Platform, Postgres + Redis + S3, plus a self-host all-in-one container. Note Postgres 17 in all-in-one vs 15 elsewhere.

## Key decisions

- Realtime (Reverb) needs a persistent process — it does not fit Lambda. This is a genuine new infrastructure requirement.
- Data residency may force a region change: eu-west-1 for a Qatar business with Qatar attendee data needs a privacy review (`65`).

## Open questions

- Is eu-west-1 acceptable, or is Middle East residency required?
- Where does Reverb run — ECS, a VM, or managed?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `104` · `65` · `71` · `85`
