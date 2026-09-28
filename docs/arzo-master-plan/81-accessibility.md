# Accessibility

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 19, 87


---

## Purpose

Making every surface usable.

## Current state

`PARTIAL`: `CONFIRMED` real WCAG 2.1 contrast math in `themeUtils.ts` with organizer guardrails (surfaces and text are not customizable, only accent) — a thoughtful design decision. Beyond that, `UNVERIFIED` — no audit found, no automated a11y tests.

## Key decisions

- Kiosks are unattended public terminals and carry the strongest accessibility obligations — height, reach, contrast, audio, timeout behaviour (`19`).
- Event-day UIs are used under pressure by tired people, which makes clarity an accessibility issue too.

## Open questions

- Target standard — WCAG 2.1 AA?
- Automated a11y testing in CI plus periodic manual audit?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `19` · `87`
