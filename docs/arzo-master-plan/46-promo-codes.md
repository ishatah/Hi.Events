# Promo and Access Codes

**Status:** WRITTEN · **Audit date:** 2026-09-29 · **Baseline:** `develop` @ `e7228c1d`
**Classification:** Keep + one extension (bulk codes) · **Priority:** P2 · **Phase:** any
**Depends on:** `13-pricing-and-promotions.md`
**Blocks:** `34-sponsor-management.md` (guest passes)

---

## Current state — `CONFIRMED`, ~90%

### Schema

```
promo_codes  id, event_id, code varchar(50),          -- stored lower-case by create/update
             discount numeric, discount_type,        -- NONE | FIXED | PERCENTAGE
             discount_applies_to,                    -- EACH_PRODUCT (default) | ORDER
             applicable_product_ids jsonb,           -- empty = all products
             expiry_date timestamptz NULL,
             max_allowed_usages NULL,
             order_usage_count, attendee_usage_count,
             timestamps, deleted_at
```

- `PromoCodeDiscountTypeEnum`: `NONE`, `FIXED`, `PERCENTAGE`.
- `PromoCodeDiscountAppliesToEnum` (`2026_07_26`): `ORDER` applies the discount once per order and
  only takes effect for `FIXED` (`PromoCodeDomainObject.php:85-88`).

### Codes are also access codes

`products.is_hidden_without_promo_code` hides a product unless a qualifying code is applied
(`ProductFilterService.php:131-133,209-210`). A `NONE`-discount code is therefore a **key to a
hidden product**, not a discount. That makes this subsystem more useful than its name suggests: VIP
tiers, partner allocations and presale windows all use it today, as the demo events show.

### Behaviour

| Aspect | Mechanism | Evidence |
|---|---|---|
| Public validation | Throttled 10/min | `api.php:641` |
| One code per order | Single `promo_code` field | `SelectProducts/index.tsx:201` |
| Shareable link | Applied code is written to and read from `?promo_code=` | `SelectProducts/index.tsx:506,541-544` |
| Usage cap | Live order count plus `isValid()` under the per-event advisory lock | `PromoCodeUsageValidationService.php:19-31`; lock at `CreateOrderHandler.php:57` |
| Increment | On order completion only, via a listener gated on `isOrderCompleted()` | `EventStatisticsIncrementService.php:419`; `UpdateEventStatsListener.php:12` |
| Decrement | On cancellation, gated on `isOrderCompleted()` and idempotent | `EventStatisticsCancellationService.php:594`, gate `:70` |
| Refund | **No decrement** | No refund path touches promo usage |

The counter-symmetry rule in `CLAUDE.md` — decrement only what was incremented — is applied
correctly. The cap is enforced from a **live order count**, not from the counter, so the counters
are reporting figures rather than the source of truth. That is the same principle `24` applies to
`entries_used`.

## Two observations, neither a defect

1. **A code that becomes invalid between validation and order creation yields no discount,
   silently.** The storefront validates on apply, so this is the race case (a code hitting its cap
   while someone is checking out). The order still succeeds, at full price. Acceptable, provided the
   order summary visibly shows no discount applied.
2. **Refunds keep the usage.** A refunded order used its code. Whether it should *free* a slot
   against `max_allowed_usages` is a policy question: capped codes are often promises ("first 50
   buyers"), and a refund reopening a slot may or may not be intended. Leave as is unless an
   organizer asks.

## The extension worth building — bulk single-use codes

Needed by sponsors (`34`) and useful elsewhere: "give this sponsor 40 complimentary passes, one code
per guest, and tell me which were used".

Today that means creating 40 codes by hand. Target:

```
promo_code_batches  id, short_id, event_id, name,
                    prefix, quantity, discount_type, discount,
                    applicable_product_ids jsonb, expiry_date NULL,
                    sponsorship_id NULL → sponsorships,      -- 34
                    created_by, timestamps

promo_codes         + promo_code_batch_id NULL → promo_code_batches
```

- Generation creates `quantity` rows with `max_allowed_usages = 1` and random suffixes after the
  prefix (`GOLD-7KQ2MX`). Codes stay individual rows so every existing path — validation, caps,
  counters, reports — works unchanged.
- The batch gives one export (code, redeemed or not, redeeming order) and one report line.
- Linking a batch to a sponsorship turns redemptions into fulfilment evidence (`34`).

## Not needed

| Idea | Why not |
|---|---|
| **Session-scoped codes** (the scaffold's question) | Sessions are free with registration (`27`); there is nothing to discount. Revisit only if paid workshops arrive. |
| Stacking multiple codes | One code per order is simpler to reason about and to support; no demand evidence |
| Automatic discounts without a code | Covered by tiered pricing and sale windows (`13`) |
| Per-customer usage limits | Only meaningful with attendee identity (`30`); email-based limits are trivially bypassed |

## Open questions

- **Should a refund free a capped slot?** A policy call per organizer; default stays "no".
- **Code format for bulk batches** — ambiguous characters (0/O, 1/I) should be excluded, since guests type them from printed invitations.
- **Do sponsors see their own batch's redemptions?** Through the sponsor report (`34`), not a login.

## Related

`13-pricing-and-promotions.md` · `34-sponsor-management.md` · `45-affiliate-referrals.md` ·
`11-ticketing-commerce.md` · `24-access-control.md`
