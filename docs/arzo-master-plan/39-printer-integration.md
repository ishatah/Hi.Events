# Printer Integration

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 37, 22  
**Blocks:** 19


---

## Purpose

Badge and label printing to physical devices.

## Current state

`MISSING`. See F8 — browser print only.

## Key decisions

- Printers are addressed through the `37` abstraction, with vendor adapters.
- Print jobs are server-created, queued, and retryable; the device pulls and reports outcome.
- Local printing must work while offline, from cached badge data.

## Open questions

- Network printers (simpler, needs venue network) vs USB-attached to a print host (works offline, more setup)?
- Zebra ZPL / Evolis / CUPS — which do we support first?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `19` · `22` · `37`
