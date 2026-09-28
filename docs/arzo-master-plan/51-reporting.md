# Reporting

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 52, 54, 108


---

## Purpose

The report catalogue and delivery.

## Current state

`PARTIAL`, 40%. `CONFIRMED`: `ReportTypes` has exactly 4 — `product_sales`, `daily_sales_report`, `promo_codes_report`, `occurrence_summary` — all revenue-oriented. Export via `app/Exports/` is solid (attendees, orders, answers, promos, affiliates).

## Key decisions

- Add attendance, session, exhibitor and operations reports once their data exists.
- Statistics rollup tables (`event_statistics` and friends) are the existing pattern to extend rather than querying raw tables.

## Open questions

- Scheduled report delivery by email?
- Do organizers need a custom report builder, or is a fixed catalogue enough?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `108` · `52` · `54`
