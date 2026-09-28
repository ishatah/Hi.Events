# Event Lifecycle

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 56  
**Blocks:** 106, 108


---

## Purpose

The full lifecycle from lead to archive.

## Current state

`PARTIAL`: the platform covers creation through execution. Lead, qualification, proposal, contract, closeout, evaluation and archive are outside it.

## Key decisions

- Model the full lifecycle with gates and evidence (`56`). The commercial phases may live in a CRM (`47`) rather than here.
- Closeout and evaluation are where repeat events get cheaper — worth building even though they feel like paperwork.

## Open questions

- How much of the pre-sales lifecycle belongs in this platform vs a CRM?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `106` · `108` · `56`
