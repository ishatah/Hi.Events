# Localization

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Blocks:** 135


---

## Purpose

Languages, formats, and RTL.

## Current state

`CONFIRMED` strong for the platform: 20 locales via Lingui, backend `lang/*.json`, timezone handling. But **no Arabic locale exists** — which is notable for a Qatar business.

## Key decisions

- Adding `ar` is more than translation: it requires RTL layout support across 378 components, which has never been exercised. Treat it as a project (`135`).
- Arabic typography needs a real Arabic face; the ARZO marketing site resolves Arabic from the OS rather than shipping a webfont.

## Open questions

- Is Arabic required for ARZO's own events? If yes, it is higher priority than this plan currently implies.
- Do attendee-facing surfaces need Arabic before organizer-facing ones? Almost certainly.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `135`
