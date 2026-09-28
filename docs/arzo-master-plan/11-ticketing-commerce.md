# Ticketing and Commerce

**Status:** WRITTEN · **Audit date:** 2026-09-28
**Classification:** Keep — do not rewrite

---

## Purpose

The strongest part of the codebase, and the part this plan most wants to leave alone. This document
exists mainly to say what **not** to touch, and why.

## Current state — `CONFIRMED`, ~90%

| Capability | State | Evidence |
|---|---|---|
| Products and categories | `CONFIRMED` | `products`, `product_categories`, `ProductType` = TICKET \| GENERAL |
| Price types | `CONFIRMED` | `ProductPriceType` = PAID \| FREE \| DONATION \| TIERED \| REGISTRATION |
| Tiered / early bird | `CONFIRMED` | Sale windows + `sequential_tier_release_enabled` |
| Add-ons | `CONFIRMED` | `product_addons` |
| Orders and line items | `CONFIRMED` | `orders`, `order_items` |
| **Concurrency control** | `CONFIRMED` | `pg_advisory_xact_lock` per event in `CreateOrderHandler:57`, and per order short id in `CompleteOrderHandler:82` |
| Tax and fees | `CONFIRMED` | `tax_and_fees` + two join tables, at product and price level |
| Invoices | `CONFIRMED` | `invoices`, `GenerateOrderInvoicePDFService` (dompdf) |
| Refunds | `CONFIRMED` | `order_refunds`, plus offline refund service |
| Audit trail | `CONFIRMED` | `order_audit_logs` |
| Capacity pools | `CONFIRMED` | `capacity_assignments`, `product_capacity_assignments` |
| Occurrence overrides | `CONFIRMED` | `product_occurrence_visibility`, `product_price_occurrence_overrides` |
| Statistics | `CONFIRMED` | Four rollup tables maintained by increment services |

### The advisory locks are the most important detail here

`CreateOrderHandler` takes `pg_advisory_xact_lock(eventId)`, serializing concurrent checkouts per
event. This is correct for consistency and is **the natural bottleneck under a sales spike** — when a
popular event opens, contention is on that lock, not on CPU.

Two consequences:

1. **Do not remove it** to chase throughput without a rigorous replacement. Overselling is worse than
   queuing.
2. **Load-test it specifically** (`125`). It is the single most likely thing to surprise someone on a
   launch day.

## What changes

Almost nothing.

| Change | Reason |
|---|---|
| Add payment providers behind the existing enum | `PaymentProviders` has only STRIPE and OFFLINE (`15`) |
| Delete or finish the Razorpay dead code | `RazorpayOrderDomainObject` exists but is not in the enum — misleading |
| Opportunistic handler slimming | `CreateOrderHandler` holds availability arithmetic and lock management that belong in domain services. Refactor when touching it, not as a project. |

## What explicitly does not change

- The order/product/price model
- Advisory-lock concurrency control
- Tax, VAT, invoice and refund logic
- The statistics rollup approach

**Sessions are not products.** `27` deliberately treats sessions as free-with-registration rather
than purchasable. Making a session sellable pulls tax, VAT, invoicing and refunds into the programme
model, and the plan defers that until a client actually needs paid workshops. Revisit before Phase 3
exit, not during.

## Open questions

- **Paid sessions** — above. The decision point is real and dated.
- **Should `attendees.checked_in_at` remain?** Once `access_logs` is authoritative (`24`), it becomes a derived convenience. Keeping it avoids rewriting reports; dropping it removes a second source of truth. Leaning keep, documented as advisory.
- **Merchant of record** (`15`): is ARZO the merchant, or a platform taking application fees? Changes PCI scope (`66`).

## Related

`13-pricing-and-promotions.md` · `14-waitlist-and-capacity.md` · `15-payments-invoicing-vat.md` ·
`27-sessions-tracks.md` · `74-performance.md` · `125-performance-testing-plan.md`
