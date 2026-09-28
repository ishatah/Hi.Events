# Promo Codes

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 13  


---

## Purpose

Existing discount system.

## Current state

`CONFIRMED` working: `promo_codes` with discount type and applies-to enums, public validation endpoint, usage counters gated on order completion.

## Key decisions

- Keep. The CLAUDE.md warning stands: decrements must be gated on `isOrderCompleted()` to stay symmetric.

## Open questions

- Session-scoped promo codes once `27` lands?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `13`
