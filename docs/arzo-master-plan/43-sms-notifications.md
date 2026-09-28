# SMS

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** `69-notifications-architecture.md`  
**Blocks:** `44-push-notifications.md`, `30-mobile-event-app.md`


---

## Purpose

Transactional and bulk SMS.

## Current state

`MISSING`, 0%. `CONFIRMED`: no Twilio/Vonage/provider code anywhere.

## Key decisions

- Add behind a notification abstraction (`69`) so email/SMS/push share audience and template logic.
- SMS is expensive per message; it belongs on high-value transactional paths (ticket delivery, gate changes, cancellations), not bulk marketing.

## Open questions

- Which provider has reliable Qatar and GCC delivery? Local routes and sender-ID registration matter.
- Sender ID registration and local regulatory requirements — lead time.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `69`
