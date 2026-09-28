# Payments, Invoicing and VAT

**Status:** WRITTEN · **Audit date:** 2026-09-29
**Classification:** Extend · **Depends on:** `11-ticketing-commerce.md` · **Blocks:** `66-compliance.md`

---

## Current state — `PARTIAL`, ~70%

Strong on money correctness, narrow on providers.

| Capability | State | Evidence |
|---|---|---|
| Stripe payments | `CONFIRMED` | `stripe_payments`, payment intents, incoming webhook |
| Stripe Connect per organizer | `CONFIRMED` | `organizer_stripe_platforms`, `account_stripe_platforms` |
| Application fees | `CONFIRMED` | `order_application_fees`, `order_payment_platform_fees` |
| Payouts | `CONFIRMED` | `stripe_payouts` |
| Offline payment | `CONFIRMED` | `OFFLINE` provider, `AWAITING_OFFLINE_PAYMENT` status, mark-as-paid |
| Refunds | `CONFIRMED` | `order_refunds`, plus an offline refund service |
| Invoices | `CONFIRMED` | `invoices`, `GenerateOrderInvoicePDFService` (dompdf) |
| Tax and fees | `CONFIRMED` | `tax_and_fees` + product-level and price-level joins |
| VAT settings | `CONFIRMED` | `account_vat_settings`, `organizer_vat_settings` |
| VAT number validation | `CONFIRMED` | `ValidateVatNumberJob` against VIES, `$tries=15` with dynamic backoff |
| Money handling | `CONFIRMED` | `brick/money` |

### Only two providers

`CONFIRMED`: `PaymentProviders` has exactly `STRIPE` and `OFFLINE`.

`RazorpayOrderDomainObject` exists in the codebase but **Razorpay is not in the enum** — it is dead
code. Either finish it or delete it; leaving it implies support that does not exist.

## What changes

### Additional providers

Add behind the existing enum and handler pattern. The structure is already right; this is
per-provider work, not restructuring.

Priority is a **market question, not a technical one**: which gateways matter for Qatar and the GCC?
Local payment methods are frequently a purchase requirement in the region, and this plan does not
know ARZO's answer.

### Offline payment at the door

Walk-in registration (`17`) needs a door-sales path. The hard rule from `71`:

> **Never take card payments offline.**

Offline card capture is a PCI and chargeback problem ARZO should not own. Offline walk-ins take cash
or defer payment, recorded as `AWAITING_OFFLINE_PAYMENT` and reconciled on sync.

### Invoices for new object types

Badge fees, booth fees and exhibitor packages (`34`) will want invoicing. The existing invoice
service is order-shaped, so either those become products (simplest) or the invoice service gains a
second source. Prefer the former — it reuses tax, VAT and refund handling for free.

## Compliance position

`CONFIRMED`: card data never touches ARZO systems, because Stripe handles it. That keeps PCI scope
minimal and is the single most valuable property of the current design. Protect it.

The unresolved question is whether ARZO is the **merchant of record** or a **platform taking
application fees**. It determines PCI SAQ level, chargeback liability and tax registration
obligations. `UNVERIFIED`, and it belongs to the business (`66`).

## Open questions

- **Merchant of record or platform?** Above. Blocks `66`.
- **Which GCC providers?** Needs the market answer.
- **Razorpay: finish or delete?** Recommend delete until someone needs it.
- **Multi-currency per event** — currency is per-event today; `brick/money` supports more. Untested at scale rather than missing.
- **Do badge and booth fees become products?** Recommended yes.

## Related

`11-ticketing-commerce.md` · `17-onsite-registration.md` · `34-sponsor-management.md` ·
`66-compliance.md` · `71-realtime-architecture.md`
