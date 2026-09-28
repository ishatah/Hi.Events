# Speakers

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 27, 23  
**Blocks:** 29


---

## Purpose

Speaker profiles, slots, and self-service.

## Current state

`MISSING`. No speaker entity.

## Key decisions

- `speakers` is separate from `attendees` and `users`: a speaker may never register, may have no account, and has public bio/photo content with a different privacy profile.
- Account-scoped and reusable across events (`event_id` nullable).

## Open questions

- Speaker portal for bio and slide upload — own surface, or an extension of the accreditation portal?
- Do speakers automatically receive a SPEAKER accreditation and credential? Probably yes; confirm the workflow.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `23` · `27` · `29`
