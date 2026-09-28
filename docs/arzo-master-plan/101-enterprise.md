# Enterprise Requirements

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 09, 100  


---

## Purpose

SSO, audit, SLA, procurement.

## Current state

`MISSING`: no SAML/OIDC/SCIM. `CONFIRMED`: JWT with `account_id` and `role` baked into the token, so a role change does not take effect until refresh — relevant to enterprise expectations.

## Key decisions

- SSO needs the RBAC work first (`09`); bolting SAML onto three roles is not useful.
- Enterprise clients will ask for data residency, retention control, and an SLA — all currently undefined.

## Open questions

- Is there a named enterprise prospect, or is this speculative? Build on demand.
- Dedicated environments — worth the operational cost?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `09` · `100`
