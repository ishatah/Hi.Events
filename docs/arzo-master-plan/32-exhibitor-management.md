# Exhibitor Management

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 09, 23, 25  
**Blocks:** 33, 34, 35, 93


---

## Purpose

Exhibitors as first-class entities with their own portal.

## Current state

`MISSING`, 0%. `CONFIRMED`: no exhibitor, sponsor, or booth entity; only demo-seeder string matches. `AffiliateDomainObject` is adjacent (referral attribution with export) but models neither exhibitors nor leads.

## Key decisions

- An exhibitor is an organization with staff, a booth, and leads — not a variant of attendee.
- Exhibitor staff get credentials through the same one-of constraint as everyone else (`23`), so access control stays uniform.
- Needs its own portal surface (`93`) and therefore real per-resource permissions (`09`).

## Open questions

- Are exhibitors billed through the platform (booth packages as products), or invoiced offline?
- Can exhibitors self-register, or does ARZO onboard them?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `09` · `23` · `25` · `33` · `34` · `35` · `93`
