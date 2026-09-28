# Affiliates and Referrals

**Status:** SCAFFOLD · **Audit date:** 2026-09-28


---

## Purpose

Existing referral attribution.

## Current state

`CONFIRMED` working: `affiliates` with sales volume and unique visitors, per-event routes, export. Counters use `incrementEach`/`decrementEach` for atomic updates.

## Key decisions

- Keep unchanged. Note the asymmetric parameter order between `incrementEach` and `decrementEach` — a documented footgun, not a defect.

## Open questions

- Should affiliate attribution extend to exhibitor-driven registrations once `32` lands?

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md`
