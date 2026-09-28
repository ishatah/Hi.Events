# Audit Logging

**Status:** SCAFFOLD · **Audit date:** 2026-09-28


---

## Purpose

Who did what, when, and why.

## Current state

`PARTIAL`, 50%. `CONFIRMED`: `order_audit_logs` and `event_logs` exist; coverage is not universal. Impersonation is logged and attached to Sentry scope.

## Key decisions

- Accreditation decisions, credential revocation, access overrides and badge reprints all need audit trails — they are contestable actions.
- `access_logs` is itself an audit trail and must never be edited (`24`).

## Open questions

- Retention period for audit logs vs operational logs?
- Does any client need tamper-evident logging?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md`
