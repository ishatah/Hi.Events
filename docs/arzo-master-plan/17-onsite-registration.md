# On-Site / Walk-In Registration

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 71, 23, 21  
**Blocks:** 19


---

## Purpose

Registering someone at the door, including payment and immediate badge issue.

## Current state

`PARTIAL`, 30%. `CreateAttendeeAction` supports manual creation with `override_capacity`, and `MarkOrderAsPaid` plus `OFFLINE` payment allow door sales — but only from the **admin backoffice**, not from the scanner or a kiosk. No at-the-gate flow exists.

## Key decisions

- Walk-in must work offline (capture locally, reconcile later) but must not take card payments offline.
- A walk-in should produce person -> attendee -> credential -> badge in one flow, not four screens.

## Open questions

- Cash handling and reconciliation at the door — is that in scope for software, or a manual process?
- How are offline-created duplicates resolved when the same person registered online? `71` says flag, never auto-merge.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `19` · `21` · `23` · `71`
