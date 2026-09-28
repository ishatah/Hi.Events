# UI/UX System

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 88  
**Blocks:** 89, 90, 91, 92, 93


---

## Purpose

Per-surface UX principles.

## Current state

`PARTIAL`: Mantine-direct throughout (289 of 378 components import it directly); the `components/common/` primitives layer is vestigial — `common/Badge` is a props-less stub rendering an empty badge. Three overlapping theme systems with inconsistent alpha values between `themeUtils` and `CheckoutThemeProvider`.

## Key decisions

- Event-day surfaces have different rules from admin surfaces: large targets, one-handed, glanceable, sunlight-readable, forgiving of mistakes.
- Consolidate the three theme systems rather than adding a fourth.
- Organizer theming currently applies as inline styles on a subtree, so portalled Mantine modals and notifications do not inherit it — fix before adding more themed surfaces.

## Open questions

- Rebuild the primitives layer, or commit to Mantine-direct and delete the vestigial wrappers? Half-measures are the current state and the worst option.
- Who owns design? Without an owner, drift resumes.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `88` · `89` · `90` · `91` · `92` · `93`
