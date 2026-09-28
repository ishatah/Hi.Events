# Registration Platform

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 12, 23


---

## Purpose

Registration flows and forms, distinct from ticketing commerce.

## Current state

`CONFIRMED` strong: `questions` / `question_answers` with 9 field types, per-order and per-ticket collection (`AttendeeDetailsCollectionMethod`), branded pages, embeddable widget.

## Key decisions

- Keep as-is. This is mature.
- Extend questions to accreditation applications rather than building a second form engine (`23`).

## Open questions

- Should the question engine support conditional logic (show X if Y)? Common request; none exists today.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `12` · `23`
