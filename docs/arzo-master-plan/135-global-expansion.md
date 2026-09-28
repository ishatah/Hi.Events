# Beyond Qatar

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 82  


---

## Purpose

What expansion requires.

## Current state

`PARTIAL`: 20 locales, multi-currency via `brick/money`, timezone handling — a good base. **No Arabic locale and no RTL support**, which is notable given the home market.

## Key decisions

- Arabic plus RTL is the first expansion requirement and arguably a home-market requirement, not expansion at all (`82`).
- Data residency and local payment methods are the other gates (`65`, `15`).

## Open questions

- Which markets, in what order?
- Is Arabic needed for ARZO's own Qatar events? If so it outranks much of this plan.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `82`
