# Networking and Meetings

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 30, 27  


---

## Purpose

Attendee-to-attendee and attendee-to-exhibitor connections.

## Current state

`MISSING`. No networking, chat, or meeting entity.

## Key decisions

- Meetings are a session subtype with a room and participants, reusing the programme model rather than a parallel one.
- Opt-in only. Attendee discoverability is a privacy decision (`65`), not a default.

## Open questions

- Is networking actually wanted by ARZO's event mix, or is it feature-following? Assess before building.
- Messaging between attendees creates moderation and data-retention obligations.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `27` · `30`
