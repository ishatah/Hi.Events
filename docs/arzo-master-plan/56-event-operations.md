# Event Operations Lifecycle

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 09  
**Blocks:** 57, 58, 59, 105, 106


---

## Purpose

Lead to closeout as a managed workflow with gates.

## Current state

`MISSING`. The platform starts at event creation; everything before (lead, proposal, contract) and much after (closeout, evaluation) is outside it.

## Key decisions

- Model the lifecycle with explicit gates, owners, evidence and audit trail — this is ARZO-as-operator, not SaaS.
- Gates matter more than tasks: go/no-go with recorded evidence is what prevents event-day surprises.

## Open questions

- Does ARZO want its sales pipeline in this system, or in a CRM (`47`)? Overlap risk.
- How much process rigour will the team actually adopt? An unused workflow is worse than none.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `09` · `105` · `106` · `57` · `58` · `59`
