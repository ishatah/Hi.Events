# Attendee Mobile App — Scope

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 27, 44  
**Blocks:** 95, 31


---

## Purpose

What the attendee app is, and what it is not.

## Current state

`MISSING`. `CONFIRMED`: no PWA — vite plugins are exactly react/lingui/copy; no service worker, no IndexedDB. `site.webmanifest` exists with `display: standalone`, making the app installable but **blank offline** — a trap, not a feature.

## Key decisions

- The agenda is the spine. Without `27` the app is a ticket wallet, so it cannot precede Phase 3.
- Ticket + QR, agenda, notifications, venue map first. Networking and engagement later.
- PWA is likely sufficient for attendees; scanners and kiosks have a stronger case for native (`71`).

## Open questions

- Native or PWA? Push notifications and offline tickets push toward native; one codebase pushes toward PWA.
- Does the attendee app need offline ticket display? Strongly yes — venue networks fail exactly when people arrive.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `27` · `31` · `44` · `95`
