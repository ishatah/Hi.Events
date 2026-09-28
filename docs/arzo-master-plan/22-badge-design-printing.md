# Badge Design and Print Pipeline

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 21, 71  
**Blocks:** 19, 39


---

## Purpose

The designer, the render pipeline, and physical printing.

## Current state

`MISSING`. `CONFIRMED`: all printing today is `window.print()` behind a 500ms timeout with `@media print` CSS in 3 files. No page-size control beyond one `@page` rule, no dialog bypass, no label/ESC-POS path, no server-side PDF endpoint. `@react-pdf/renderer` is a **production dependency with zero imports** (F8).

## Key decisions

- Server-side render to PDF is authoritative; a browser path cannot bypass the print dialog and is unusable for badge-on-demand.
- `TicketDesigner`'s mock-data preview pattern is a good template to reuse for badge preview.
- Print jobs are queued and retryable — a jam must not lose the badge (`128` gate 21).
- Either use the already-installed `@react-pdf/renderer` or remove it; do not leave it dead.

## Open questions

- Which printer families? Zebra/Evolis dominate badge printing and speak their own languages. Affects `39` and `102`.
- Direct-to-printer from an offline device, or via a local print host? A print host is more reliable and more hardware.
- Badge stock, lanyards and holders are procurement, not code (`102`).

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `19` · `21` · `39` · `71`
