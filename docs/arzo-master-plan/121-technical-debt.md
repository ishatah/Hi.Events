# Technical Debt Register

**Status:** SCAFFOLD · **Audit date:** 2026-09-28

**Depends on:** 02  


---

## Purpose

Known debt, with cost and consequence.

## Current state

Compiled from both audits. Every item is `CONFIRMED` unless marked.

## Key decisions

- **High:** static tenant state with no global scopes (F11); imperative-only authorization with a no-op default role gate (F12); two check-in write models (F10); SSR cross-request singletons (F5); redundant `process.env` define, now removed (F6).
- **Medium:** webhook payloads coupled to API resources (F14); `BaseRepository` stateful builder with `@deprecated resetModel` hinting at subclass misuse (F15); two disagreeing hydration paths; queue-name config confusion (F13); dev/prod/e2e divergence and Postgres 15 vs 17 (F7); no token refresh; `@react-pdf/renderer` dead dependency; Razorpay dead code; `public/widget.js` a committed build artifact.
- **Low:** vestigial primitives layer with a stub `common/Badge`; three overlapping theme systems with inconsistent alphas; duplicated LQIP logic; `schema.sql` misleadingly named; stale `failed_jobs` generated domain object; dead `routes/channels.php` referencing a non-existent namespace; `timestamp` vs `timestamptz` inconsistency (new programme tables deliberately diverge).
- **`UNVERIFIED` and worth a targeted pass:** which of the 59 repository subclasses build custom queries without `runQuery()` — the highest-leverage latent-bug area in the backend.

## Open questions

- Pay debt down opportunistically, or as a dedicated Phase 0 (this plan chose Phase 0 for the high items)?
- Is the `HiEvents\` namespace rename ever worth doing? Currently judged no — 1,659 files, zero user benefit.

## Status of this document

SCAFFOLD. Purpose, verified current state, decisions and open questions are captured.
Expand to executable depth before the work is scheduled — see `137-engineering-work-packages.md`.

## Related

`00-master-index.md` · `02-current-state-audit.md` · `02`
