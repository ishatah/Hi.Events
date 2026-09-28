# Exhibitor Lead Capture

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 32, 71  
**Blocks:** 34


---

## Purpose

Scanning attendee badges to capture and qualify leads.

## Current state

`MISSING`.

## Key decisions

- A lead is a scan event plus qualification data, recorded against exhibitor + attendee. Reuses the scan path.
- Attendees must consent to lead capture — scanning a badge transfers personal data to a third party. This is a PDPL/GDPR obligation, not a nicety (`65`).
- Must work offline; exhibition halls have the worst connectivity.

## Open questions

- What qualification schema? Free-form notes, structured fields, or hot/warm/cold?
- Lead delivery: real-time portal, or post-event export? Both, probably.
- Lead scoring needs training data that does not exist yet (`241`).

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `32` · `34` · `71`
