# Staff Surface

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 09, 40, 57  
**Blocks:** 97


---

## Purpose

What event staff use.

## Current state

`PARTIAL`: the check-in scanner exists but has **no staff identity at all** — the URL short ID is the only credential, so no scan can be attributed to an operator (F3).

## Key decisions

- Staff need real identity, both for accountability and to scope what they may do (`09`).
- Replace capability-URL auth with device enrolment plus staff PIN or login (`40`).
- The current link-sharing convenience is genuinely useful for volunteers — the replacement must stay nearly as easy or it will be worked around.

## Open questions

- PIN, badge scan, or login for staff identity at a shared device? Badge scan is elegant and needs `23` first.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `09` · `40` · `57` · `97`
