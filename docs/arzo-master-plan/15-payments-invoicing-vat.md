# Payments, Invoicing, VAT

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 11  
**Blocks:** 66


---

## Purpose

Payment providers, invoices, tax handling.

## Current state

`PARTIAL`: `PaymentProviders` enum has **only** `STRIPE` and `OFFLINE`. `RazorpayOrderDomainObject` exists but is not in the enum — dead code. Invoices, VAT settings, refunds, application fees and payout tracking are `CONFIRMED` and solid.

## Key decisions

- Add providers behind the existing enum + handler pattern; do not restructure payments.
- Delete the Razorpay dead code or finish it — leaving it is misleading.
- Never take card payments offline (`71`). Offline walk-ins take cash or defer.

## Open questions

- Which providers matter for Qatar? Local gateway support may be a market requirement.
- Is ARZO a merchant of record, or a platform taking application fees? Changes compliance scope (`66`).

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `11` · `66`
