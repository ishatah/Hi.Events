# Design System

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 87


---

## Purpose

Tokens, components, and the ARZO visual language.

## Current state

`PARTIAL`: ARZO brand tokens now applied (accent `#1B7E99`, SF Pro Display) via env-driven Mantine palette generation. No component library, no token module, no documented usage rules.

## Key decisions

- Tokens first, components second. The current inconsistency comes from having neither.
- The ARZO brand is deliberately austere (one accent, hairline borders, square corners, no shadows). Applying that to a dense admin dashboard needs judgment, not literal translation — a data table with no borders is unreadable.

## Open questions

- Full ARZO design language in the admin app, or brand-layer only (the current state)?
- Component documentation — Storybook, or in-repo?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `87`
