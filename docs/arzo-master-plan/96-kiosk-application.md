# Kiosk Application

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 19, 71, 40  


---

## Purpose

The unattended terminal app.

## Current state

`MISSING`.

## Key decisions

- Kiosk mode means locked down: no browser chrome, no navigation away, session timeout to an attract screen, and recovery without a keyboard.
- Must print locally and work offline (`19`, `71`).

## Open questions

- Which platform? Android kiosk mode is cheapest; iPad is more reliable hardware; Windows suits printer drivers.
- Remote monitoring and restart when nobody is next to it (`40`).

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `19` · `40` · `71`
