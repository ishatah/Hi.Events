# Event Documentation

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 56  
**Blocks:** 108


---

## Purpose

The durable record of an event.

## Current state

`PARTIAL`: `event_logs` and `order_audit_logs` exist; there is no event dossier concept.

## Key decisions

- Aggregate the record — plan, contracts, readiness, incidents, attendance, reports — into one retrievable dossier per event.
- This is what makes a repeat event cheaper to run than the first.

## Open questions

- Retention period for event dossiers containing personal data (`65`)?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `108` · `56`
