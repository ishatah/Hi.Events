# Integration Framework

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 47, 48  


---

## Purpose

A general pattern for third-party connections.

## Current state

`MISSING`. Stripe is integrated directly; there is no general framework.

## Key decisions

- Adapter pattern with per-provider credentials, scoped tokens, and retry/backoff shared.
- Do not build the framework before the second real integration exists — one integration does not justify an abstraction.

## Open questions

- Which integrations are actually required vs speculative?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `47` · `48`
