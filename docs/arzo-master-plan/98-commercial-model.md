# Commercial Model

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 04  
**Blocks:** 99, 100


---

## Purpose

How ARZO makes money from this.

## Current state

`PARTIAL`: SaaS mode exists (`APP_SAAS_MODE_ENABLED`, Stripe application fees, messaging tiers) alongside self-host. ARZO also runs its own events, making it both operator and vendor.

## Key decisions

- The dual role is the central tension: features valuable to ARZO's operations may not be sellable SaaS, and vice versa. Decide per capability rather than assuming both.
- The AGPL licence constrains closed-source SaaS (`01`) — resolve before Phase 2.

## Open questions

- Is external SaaS actually a goal, or is this internal tooling with a ticketing product attached?
- Services revenue (ARZO running events) vs licence revenue — which dominates?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `04` · `100` · `99`
