# Security Testing Plan

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 64, 79  


---

## Purpose

Proving the security claims.

## Current state

`PARTIAL`: the authorization layer is effectively untested (7 of 268 Actions have Feature tests), and the `SecureCallWebhookJob` SSRF defences appear untested.

## Key decisions

- Highest-value tests first: cross-tenant denial, missing-authorization detection, SSRF defence, credential forgery resistance, offline replay abuse.
- Penetration testing before any external SaaS launch, not after.

## Open questions

- Internal or external pen test?
- Bug bounty — premature at this stage?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `64` · `79`
