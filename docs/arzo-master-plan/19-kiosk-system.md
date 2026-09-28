# Kiosk System

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 71, 40, 22  
**Blocks:** 96


---

## Purpose

Unattended self-service check-in, registration, and badge printing.

## Current state

`MISSING`. The scanner is staff-operated; there is no kiosk mode, no attract screen, no lockdown.

## Key decisions

- Kiosk is a distinct application, not a responsive mode of the scanner — different trust model, different failure handling, no operator present.
- Must be offline-capable (`71`) and must print badges locally (`22`).
- Needs device-level identity (`40`), never a user session.

## Open questions

- Hardware: tablet + enclosure + printer, or an integrated kiosk unit? Affects `102`.
- Accessibility is a legal consideration for unattended public terminals — height, contrast, audio. See `81`.
- What happens when the printer jams and nobody is watching?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `22` · `40` · `71` · `96`
