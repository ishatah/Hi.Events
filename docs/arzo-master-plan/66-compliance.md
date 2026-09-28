# Compliance

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 15, 65  


---

## Purpose

PCI, local regulation, accessibility obligations.

## Current state

`PARTIAL`: Stripe handles card data so PCI scope is minimized. `UNVERIFIED` whether any formal compliance review has occurred.

## Key decisions

- Keep card data out of ARZO systems entirely — this is why offline card payment is excluded (`71`).
- Determine PCI SAQ level based on the merchant-of-record question in `15`.

## Open questions

- Qatar-specific event, ticketing or consumer regulation — `UNVERIFIED`, needs local advice.
- Accessibility: is there a legal standard ARZO must meet for public-facing kiosks?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `15` · `65`
