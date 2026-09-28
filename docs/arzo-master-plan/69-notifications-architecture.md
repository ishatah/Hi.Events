# Notification Architecture

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 43, 44


---

## Purpose

One bus for email, SMS, push, and in-app.

## Current state

`PARTIAL`: email is mature (30 mailables, templates, scheduling). SMS and push are `MISSING`. `announcements` exist but target **platform users, not attendees** — so there is no attendee in-app channel.

## Key decisions

- One abstraction over channels, with audience, template and preference logic shared. Building SMS and push as separate silos would triplicate the audience code.
- Respect `BaseMail`'s queued + `afterCommit()` semantics; a notification inside a rolled-back transaction must not send.

## Open questions

- Notification preference granularity — per channel, per category, or both?
- Does the attendee in-app channel reuse `announcements`, or need its own model?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `43` · `44`
