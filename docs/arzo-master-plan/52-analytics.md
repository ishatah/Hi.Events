# Analytics Architecture

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 51  
**Blocks:** 54, 55


---

## Purpose

How analytical data is computed and stored.

## Current state

`PARTIAL`: four denormalized rollup tables maintained by jobs and increment services. No OLAP store, no metrics backend.

## Key decisions

- Keep the rollup-table approach while Postgres serves; it is simple and already proven here.
- Derive attendance metrics from `access_logs` and `session_attendance` rather than adding counters, which drift under offline replay.
- Demographics require consented, structured collection — not inferred from names or free-text answers.

## Open questions

- At what event size does Postgres stop serving the command center? Needs load modelling (`74`).
- Is a separate analytics store justified, or premature?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `51` · `54` · `55`
