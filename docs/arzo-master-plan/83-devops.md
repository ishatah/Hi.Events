# DevOps

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 85, 129


---

## Purpose

Pipeline, environments, and developer experience.

## Current state

`PARTIAL`, 75%. `CONFIRMED`: 5 GitHub workflows — backend tests across PHP 8.3/8.4/8.5, E2E with sharding and a nightly cron, Vapor + DigitalOcean deploy, multi-arch image publish, CLA. **No frontend lint/typecheck/test job.** Dev queue worker and scheduler are un-supervised `docker exec -d` processes that die on container restart.

## Key decisions

- Add frontend gates to CI (Phase 0).
- Make dev queue worker and scheduler real compose services so dev matches prod.
- Pin Postgres to one version across dev, e2e, and prod (currently 15 vs 17).

## Open questions

- Should the e2e stack run a queue worker? Today it does not, so queued-email flows behave differently there.
- Preview environments per PR?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `129` · `85`
