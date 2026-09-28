# Push Notifications

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 30, 69  


---

## Purpose

Push to the attendee and staff apps.

## Current state

`MISSING`. No FCM/APNs/web-push.

## Key decisions

- Push requires the app (`30`) and the notification bus (`69`); it cannot land earlier.
- Event-day push (gate change, session moved) is the high-value case — treat it as operational, not marketing.

## Open questions

- Web push vs native push decides the PWA/native question in `30`.
- Notification preference management to avoid opt-outs from over-sending.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `30` · `69`
