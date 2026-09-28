# Hardware Abstraction Layer

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 71  
**Blocks:** 38, 39, 40, 36


---

## Purpose

A vendor-swappable interface for every physical device.

## Current state

`MISSING`, 0%. `CONFIRMED`: the only hardware-adjacent code is browser `getUserMedia` QR scanning and a USB/HID keypress wedge in the check-in UI.

## Key decisions

- One interface per device class — Scanner, Printer, CredentialEncoder, Kiosk — with vendor adapters behind it. Swapping a printer brand must not touch application code.
- The abstraction is defined by what ARZO needs, not by what one vendor's SDK exposes. Vendor-shaped interfaces are how lock-in happens.
- Every adapter must be testable with a fake, so the app is developable without hardware present (`79`).

## Open questions

- Where does the adapter run — in the device app, or a local print/bridge host? A bridge host is more reliable and more hardware to manage.
- Do we commit to specific vendors now for procurement lead time, at the cost of flexibility?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `36` · `38` · `39` · `40` · `71`
