# Attendee Management

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 23


---

## Purpose

Attendee lifecycle, editing, self-service.

## Current state

`CONFIRMED`: CRUD, export, self-service edit gated by `allow_attendee_self_edit`, magic-link ticket lookup via `ticket_lookup_tokens`.

## Key decisions

- Add nullable `person_id` (`23`); leave everything else.
- `attendees.checked_in_at` becomes advisory once `access_logs` is authoritative (`24`).

## Open questions

- Should attendee edit history be surfaced to organizers? `order_audit_logs` exists as a pattern.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `23`
