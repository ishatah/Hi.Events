# RSVP

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 10, 11  


---

## Purpose

Free confirm-attendance flow, distinct from ticketed checkout.

## Current state

`MISSING` as a distinct flow. Free tickets exist (price 0) but traverse the full order/checkout pipeline, which is heavier than an RSVP needs and produces order artifacts for a yes/no answer.

## Key decisions

- Model RSVP as a lightweight path that still produces an attendee and a credential, so downstream access control is uniform.
- Reuse the questions engine for RSVP fields.

## Open questions

- Does RSVP need plus-ones / party size? Common for private events.
- Should an RSVP be convertible to a paid order without re-registration?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `10` · `11`
