# Ticketing and Commerce

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 13, 14, 15


---

## Purpose

Products, prices, orders, checkout.

## Current state

`CONFIRMED` the strongest area: `products` / `product_prices` / `orders` / `order_items`, `ProductPriceType` with TIERED, Postgres advisory locks serializing concurrent checkout per event, tax/VAT, invoices, refunds, audit logs.

## Key decisions

- Do not rewrite. Extend only.
- The checkout handler is fat (concurrency control + availability arithmetic inline). Refactor toward domain services opportunistically, not as a project.

## Open questions

- Should sessions become sellable products (paid workshops)? Pulls tax/VAT into the programme model. `27` assumes not for now.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `13` · `14` · `15`
