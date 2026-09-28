# SaaS Packaging

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 08, 98  
**Blocks:** 101


---

## Purpose

Packaging for external tenants.

## Current state

`PARTIAL`: account/organizer tenancy, white-label env vars, messaging tiers, per-account verification.

## Key decisions

- Tenant isolation must be hardened before selling to external tenants at scale (F11, `08`). Today it rests on discipline with no negative test.
- Custom domains and per-tenant branding already partly exist via env-driven theming.

## Open questions

- Which capabilities are tier-gated?
- Self-serve signup for the full on-site suite, or sales-led only? On-site needs hardware, so probably sales-led.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `08` · `101` · `98`
