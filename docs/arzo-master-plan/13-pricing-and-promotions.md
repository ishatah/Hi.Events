# Pricing and Promotions

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** Keep · **Depends on:** `11-ticketing-commerce.md`

---

## Current state — `CONFIRMED`, ~88%

Mature. This document mostly records why no work is scheduled.

| Capability | State | Evidence |
|---|---|---|
| Price types | `CONFIRMED` | `ProductPriceType` = `PAID` \| `FREE` \| `DONATION` \| `TIERED` \| `REGISTRATION` |
| Multiple prices per product | `CONFIRMED` | `product_prices`, 5 inbound refs |
| Sale windows | `CONFIRMED` | Per-price start/end |
| Sequential tier release | `CONFIRMED` | `sequential_tier_release_enabled` on products |
| Per-occurrence overrides | `CONFIRMED` | `product_price_occurrence_overrides` |
| Promo codes | `CONFIRMED` | `promo_codes`, `PromoCodeDiscountTypeEnum`, `PromoCodeDiscountAppliesToEnum` |
| Order-level discount records | `CONFIRMED` | `order_discount_codes` |
| Public validation endpoint | `CONFIRMED` | `api.php` — promo validated before checkout |
| Usage counters | `CONFIRMED` | Atomic via `incrementEach`/`decrementEach` |

### Early bird needs no feature

A common request that is already expressible: a `TIERED` price with a sale window **is** early-bird
pricing. `sequential_tier_release_enabled` additionally forces tiers to open in order rather than by
date, which covers "release the next tier when the previous sells out".

Building a separate "early bird" concept would add a second way to express the same thing.

## Two correctness rules to preserve

Both are recorded in CLAUDE.md and are easy to break accidentally:

1. **Promo usage decrements must be gated on order completion.** Usage, `products.sales_volume` and
   affiliate counters increment only when an order **completes**; any decrement must check
   `isOrderCompleted()` or the counters drift permanently.
2. **Use `incrementEach`/`decrementEach`, not read-modify-write.** These emit a single atomic
   `UPDATE ... SET col = col + n`, bypassing Eloquent events and timestamps. Note the **asymmetric
   parameter order** between the two — `incrementEach($columns, $extra, $where)` versus
   `decrementEach($where, $columns, $extra)`. A documented footgun, not a defect.

## What changes

Nothing scheduled. Two candidate extensions, neither urgent:

| Candidate | Assessment |
|---|---|
| Session-scoped promo codes | Only meaningful once sessions are sellable, which `11` and `27` deliberately defer |
| Zone-scoped pricing (pay more for VIP zone access) | Interesting for ARZO's event mix, but it couples pricing to the access model. Revisit after Phase 2. |

## Open questions

- **Should a promo code be restrictable to an accreditation type?** "Press discount" is a real ask and would couple `promo_codes` to `accreditation_types` (`23`). Cheap once both exist.
- **Currency handling for multi-currency events** — `brick/money` is a dependency and currency is per-event today. Not a gap, just untested at multi-currency scale.

## Related

`11-ticketing-commerce.md` · `14-waitlist-and-capacity.md` · `46-promo-codes.md` ·
`27-sessions-tracks.md`
