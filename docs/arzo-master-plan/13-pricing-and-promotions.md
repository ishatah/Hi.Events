# Pricing and Promotions

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 11  


---

## Purpose

Tiers, early bird, promo codes, discounts.

## Current state

`CONFIRMED` strong: `TIERED` price type with sale windows and sequential tier release; `promo_codes` with type/applies-to enums; `affiliates` for attribution.

## Key decisions

- Keep. Early bird is already expressible as a tiered price with a sale window — no separate feature needed.

## Open questions

- Should promo codes be scopeable to sessions once `27` lands?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `11`
