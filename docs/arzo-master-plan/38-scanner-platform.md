# Scanner Platform

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 37, 24, 71  
**Blocks:** 94


---

## Purpose

Every way a credential can be read.

## Current state

`PARTIAL`, 70% for QR specifically. `CONFIRMED`: camera scanning via `qr-scanner` (1 scan/sec, torch, multi-camera) and a USB/HID keyboard-wedge reader accepting codes matching `A-` prefix. Both online-only, with the F1 defect on failure.

## Key decisions

- Keep the two existing modes; add Bluetooth and dedicated-device adapters behind `37`.
- The scanner must hold the access decision function locally (`24`) so it works offline.
- Fix the dedupe-before-await ordering (F1) as part of this work, not after.

## Open questions

- The `A-` prefix check is a hardcoded assumption about identifier format. New credential types must not break it.
- Which dedicated scanner hardware? Affects `102`.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `24` · `37` · `71` · `94`
