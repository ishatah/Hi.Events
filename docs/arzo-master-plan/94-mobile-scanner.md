# Scanner Application

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 71, 38, 40  


---

## Purpose

The dedicated scanning app.

## Current state

`PARTIAL` as a web route: camera plus USB/HID modes, 3,752 LOC, genuinely thoughtful (sounds, haptics, undo, occurrence filter) — but **online-only** with F1 data loss and no operator identity.

## Key decisions

- Native is the leaning: encrypted-at-rest storage for the roster, background sync, hardware scanner access, and reliable offline behaviour. A PWA cannot match the encryption story for a device carrying attendee PII.
- Must implement the identical access decision function as the server (`24`).

## Open questions

- Native (one more codebase) vs PWA (weaker guarantees)? This is the key Phase 4 decision.
- Do we keep the web scanner as a fallback for volunteers?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `38` · `40` · `71`
