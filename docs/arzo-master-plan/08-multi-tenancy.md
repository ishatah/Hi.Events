# Multi-Tenancy

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 02, 09  
**Blocks:** 09, 48, 100, 101


---

## Purpose

Tenant model, isolation guarantees, and how they are enforced rather than assumed.

## Current state

`PARTIAL` and the highest-risk area found. `CONFIRMED`: no Eloquent global scopes anywhere; child resources scoped by parent only (`GetProductsHandler` filters `findByEventId` with no `account_id`); tenant id held in **static mutable state** (`User::$currentAccountId`) set by middleware; `RuntimeException` when unset rather than a deny. Isolation rests entirely on 159 hand-written `isActionAuthorized` calls, and `UNVERIFIED` whether that set is complete — no cross-tenant test exists.

## Key decisions

- Add a tenant global scope to every `account_id`-bearing model, converting 268 judgment calls into one invariant. Defence in depth, not a replacement for authorization.
- Replace static tenant state with request-scoped context so queue workers and console commands cannot inherit a stale tenant.
- Hierarchy stays `Account -> Organizer -> Event`, with teams and per-event roles added in `09`.

## Open questions

- Do venues cross tenant boundaries? `25` assumes no. A shared platform venue registry would be convenient and is a tenancy hole.
- Should tenant isolation be enforced at the database level (RLS) for high-assurance clients?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `02` · `09` · `100` · `101` · `48`
